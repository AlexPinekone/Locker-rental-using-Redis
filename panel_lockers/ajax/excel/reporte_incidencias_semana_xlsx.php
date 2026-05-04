<?php

	session_start();
	
  require($_SERVER['DOCUMENT_ROOT'].'/comunes/conectar.php');
  require('../SimpleXLSXGen.php');
    
  $fecha_i=$_GET['fecha_i'];
  $fecha_f=$_GET['fecha_f'];
  $ciclo=$_SESSION['ciclo'];

  $fecha_inicial = new DateTime($fecha_i);
  $fecha_final = new DateTime($fecha_f);

  // Date period no agrega la fecha final por eso se agrega un dia
  $fecha_final->modify('+1 day');

  $intervalo = new DateInterval('P1D'); // 1 día
  $periodo = new DatePeriod($fecha_inicial, $intervalo, $fecha_final);

  $columnas = [
    ['rpe', 'nombre']
  ];

  $fechas = [];
  foreach ($periodo as $fecha)
  {
    if ($fecha->format('w') == 0)
    {
      // Es domingo y lo omitimos
      continue;
    }
    $columnas[0][]=$fecha->format('Y-m-d').' ';
    $fechas[] = $fecha->format('Y-m-d');
  }
  $columnas[0][]='Porcentaje';

  $result_p=mysqli_query($dbh, "select rpe, concat(ape_pat, ' ', ape_mat, ' ', nombres) as nombre
                                from fcq.fcq_profesor as p
                                where EXISTS (
                                    select 1
                                    from hojas.ha_hoja as h
                                    where h.rpe = p.rpe and h.ciclo = '$ciclo'
                                )");

  while($row_p=mysqli_fetch_array($result_p))
  {
    $rpe=$row_p['rpe'];
    // Arreglo final se llena con los datos del profesor
    $arregloFinal = [$rpe, $row_p['nombre']];
    $datos_filtrados = [];
    $datos_por_dia = [];

    $result=mysqli_query($dbh, "select id, rpe, ciclo, hora_ini, hora_fin, lu, ma, mi, ju, vi, sa
                                from hojas.ha_hoja
                                where ciclo='$ciclo' and rpe=$rpe
                                order by hora_ini");

    $existe=mysqli_num_rows($result);

    if($existe)
    {
      // Agrupar por día
      $horario = array();
      // Días que vamos a revisar
      $dias = ['lu', 'ma', 'mi', 'ju', 'vi', 'sa'];
      // Agrupar horas por día
      $horasPorDia = [];

      while ($row=$result->fetch_assoc())
      {
        foreach ($dias as $dia)
        {
          if ($row[$dia])
          {
            for ($h = $row['hora_ini']; $h < $row['hora_fin']; $h++) {
              $horasPorDia[$dia][] = $h;
            }
          }
        }
      }

      $resultado = [];

      foreach ($horasPorDia as $dia => $horas)
      {
        sort($horas);
        $bloques = [];
        $inicio = $horas[0];
        $anterior = $horas[0];

        for ($i = 1; $i < count($horas); $i++)
        {
          if ($horas[$i] == $anterior + 1) 
          {
            $anterior = $horas[$i];
          } 
          else 
          {
            $bloques[] = [$inicio, $anterior + 1];
            $inicio = $horas[$i];
            $anterior = $horas[$i];
          }
        }
        // Añadir el último bloque
        $bloques[] = [$inicio, $anterior + 1];
        $resultado[$dia] = $bloques;
      }

      $horarios_ordenados = [];

      foreach ($dias as $dia)
      {
        if (isset($resultado[$dia]))
        {
          $horarios_ordenados[$dia] = $resultado[$dia];
        }
      }

      $horas_dia_horario=[];

      foreach ($horarios_ordenados as $dia => $bloques)
      {
        $totalHoras = 0;

        foreach ($bloques as $bloque)
        {
          if (count($bloque) == 2)
          {
            $inicio = $bloque[0];
            $fin = $bloque[1];
            $totalHoras += $fin - $inicio;
          }
        }

        $horas_dia_horario[$dia] = $totalHoras;
      }

      $result=mysqli_query($dbh, "select * from hojas.ha_checadas
                                where ciclo='$ciclo' and rpe=$rpe and fecha >= '$fecha_i' and fecha <= '$fecha_f'
                                order by fecha, hora asc");

      $existe=mysqli_num_rows($result);

      if($existe)
      {
        // Agrupar por día
        while ($row = $result->fetch_assoc())
        {
          $fecha_hora_str = $row['fecha'] . ' ' . $row['hora'];
          $timestamp = strtotime($fecha_hora_str);
          $dia = $row['fecha'];

          $row['timestamp'] = $timestamp;

          $row['dia'] = $dia;

          $datos_por_dia[$dia][] = $row;
        }

        // Filtrar por intervalos de 10 minutos
        foreach ($datos_por_dia as $dia => $registros) {

          $ultimo_incluido = null;

          foreach ($registros as $registro)
          {
            if ($ultimo_incluido === null)
            {
              // Primer registro del día
              $datos_filtrados[] = $registro;
              $ultimo_incluido = $registro['timestamp'];
            } 
            else 
            {
              // Si han pasado 10 minutos o más
              if (($registro['timestamp'] - $ultimo_incluido) >= 600)
              {
                $datos_filtrados[] = $registro;
                $ultimo_incluido = $registro['timestamp'];
              }
            }
          }
        }

        // Función para ordenar
        usort($datos_filtrados, function($a, $b) use ($dias)
        {
          // // Comparar por fecha y hora
          $datetimeA = strtotime($a['fecha'] . ' ' . $a['hora']);
          $datetimeB = strtotime($b['fecha'] . ' ' . $b['hora']);
          
          return $diaA - $diaB;
        });

        $datosPorDia = [];
        foreach ($datos_filtrados as $dato)
        {
          $dia = $dato['dia'];
          if (!isset($datosPorDia[$dia]))
          {
            $datosPorDia[$dia] = [];
          }
          $datosPorDia[$dia][] = $dato;
        }

        // Resultado final
        $horasPorDia = [];

        foreach ($datosPorDia as $fechaD => $registros)
        {
          $totalHoras = '00:00:00';

          // Obtener solo las horas como strings
          $horas = array_map(fn($r) => $r['hora'], $registros);

          $contadorH = count($horas);

          // Procesar por pares
          for ($i = 0; $i < $contadorH - 1; $i += 2)
          {
            $inicio = $horas[$i];
            $fin = $horas[$i + 1];

            $diferencia = restarHoras($fin, $inicio);  // diferencia de horas por bloque
            $totalHoras = sumarHoras($totalHoras, $diferencia);  // Y sumamos el nuevo bloque, si hay
          }

          $horasPorDia[$fechaD] = $totalHoras;
        }

        $sumTiempo='00:00';
        $sumhorario='00:00';
        // Recorremos los días del mes
        foreach ($fechas as $fechaM) 
        {
          $indiceDia = date('N', strtotime($fechaM));
          $diaSemana=$dias[$indiceDia-1];
          if(isset($horas_dia_horario[$diaSemana]))
          {
            $horarioDia=$horas_dia_horario[$diaSemana];
          }
          else
          {
            $horarioDia=0;
          }

          $sumhorario=sumarHoras($sumhorario, $horarioDia);

          if (isset($horasPorDia[$fechaM]))
          {
            $arregloFinal[] = substr($horasPorDia[$fechaM], 0, 5).' / '.$horarioDia;
            $sumTiempo=sumarHoras($sumTiempo, $horasPorDia[$fechaM]);
          } 
          else 
          {
            $arregloFinal[] = '00:00'.' / '.$horarioDia;
          }
        }

        // $arregloFinal[]=substr($sumTiempo, 0, 5)."/".substr($sumhorario, 0, 5) ;
        $arregloFinal[]=porcentajeHoras($sumTiempo, $sumhorario);
        $columnas[]=$arregloFinal;
      }
      else
      {
        // $error=['Error no se encontraron checadas del rpe '.$rpe];
        // $columnas[]=$error;
      }
    }
    else
    {
      // $error=['Error no se encontraron horarios del rpe '.$rpe];
      // $columnas[]=$error;
    }
  }

  $xlsx=SimpleXLSXGen::fromArray($columnas);  
  $xlsx->downloadAs('reporte_incidencias.xlsx');
  
  mysqli_close($dbh);

  function restarHoras($hora1, $hora2)
  {
    // Convertir a timestamps
    list($h1, $m1, $s1) = explode(":", $hora1);
    list($h2, $m2, $s2) = explode(":", $hora2);

    $segundos1 = ($h1 * 3600) + ($m1 * 60) + $s1;
    $segundos2 = ($h2 * 3600) + ($m2 * 60) + $s2;

    $resultado = $segundos1 - $segundos2;

    if ($resultado < 0) {
        $resultado = abs($resultado);
        $signo = "-";
    } else {
        $signo = "";
    }

    // Convertir de nuevo a formato HH:MM:SS
    $horas = floor($resultado / 3600);
    $minutos = floor(($resultado % 3600) / 60);
    $segundos = $resultado % 60;

    return sprintf("%s%02d:%02d:%02d", $signo, $horas, $minutos, $segundos);
  }

  function sumarHoras($hora1, $hora2)
  {
    // Convertir a timestamps
    list($h1, $m1, $s1) = explode(":", $hora1);
    list($h2, $m2, $s2) = explode(":", $hora2);

    $segundos1 = ($h1 * 3600) + ($m1 * 60) + $s1;
    $segundos2 = ($h2 * 3600) + ($m2 * 60) + $s2;

    $resultado = $segundos1 + $segundos2;

    if ($resultado < 0) {
        $resultado = abs($resultado);
        $signo = "-";
    } else {
        $signo = "";
    }

    // Convertir de nuevo a formato HH:MM:SS
    $horas = floor($resultado / 3600);
    $minutos = floor(($resultado % 3600) / 60);
    $segundos = $resultado % 60;

    return sprintf("%s%02d:%02d:%02d", $signo, $horas, $minutos, $segundos);
  }

  function porcentajeHoras($horaAcum, $horaTotal)
  {
    // Convertir a timestamps
    list($h1, $m1, $s1) = explode(":", $horaAcum);
    list($h2, $m2, $s2) = explode(":", $horaTotal);

    $segundos1 = ($h1 * 3600) + ($m1 * 60) + $s1;
    $segundos2 = ($h2 * 3600) + ($m2 * 60) + $s2;

    if($segundos2==0)
    {
      return '0%';
    }else
    {
      $resultado=($segundos1/$segundos2)*100;
    }

    return round($resultado, 2).'%';
  }
	
?>
