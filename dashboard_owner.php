<?php
// Conexión a la base de datos
$conn = new mysqli('localhost', 'root', '', 'beach2');

// Verificar la conexión
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

// Configuración de la zona horaria
date_default_timezone_set('America/Santiago');

// Función para obtener y agrupar datos por sucursal
function obtenerDatosPorSucursal($conn, $tabla, $sucursal_id_col) {
    $sql = "SELECT sucursales.rubro, CONCAT(sucursales.nombre, ' - ', sucursales.direccion) AS sucursal, $tabla.monto, $tabla.descripcion, $tabla.fecha 
            FROM $tabla 
            JOIN sucursales ON $tabla.$sucursal_id_col = sucursales.id";
    $result = $conn->query($sql);

    $data = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $data[$row['rubro']][] = $row;
        }
    }
    return $data;
}

// Obtener datos de cada tabla
$ventas = obtenerDatosPorSucursal($conn, 'ventas', 'sucursal_id');
$retiros = obtenerDatosPorSucursal($conn, 'retiros', 'sucursal_id');
$gastos = obtenerDatosPorSucursal($conn, 'gastos', 'sucursal_id');

// Obtener datos de sueldos (sin sucursal_id)
$sql_sueldos = "SELECT usuarios.nombre AS usuario, sueldos.monto, sueldos.fecha_pago 
                FROM sueldos 
                JOIN usuarios ON sueldos.usuario_id = usuarios.id";
$result_sueldos = $conn->query($sql_sueldos);

$sueldos = [];
if ($result_sueldos->num_rows > 0) {
    while ($row = $result_sueldos->fetch_assoc()) {
        $sueldos[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard de Finanzas</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

    <style>
        .chart-container {
            position: relative;
            height: 300px;
            max-width: 100%;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto mt-10 px-4">
        <h1 class="text-3xl font-bold text-center mb-10">Dashboard de Finanzas</h1>

        <!-- Sección de Gráficos -->
        <div class="mb-10">
            <h2 class="text-2xl font-bold mb-5">Resumen General</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white p-4 rounded-lg shadow-md">
                    <div class="chart-container">
                        <canvas id="chart-ventas"></canvas>
                    </div>
                </div>
                <div class="bg-white p-4 rounded-lg shadow-md">
                    <div class="chart-container">
                        <canvas id="chart-retiros"></canvas>
                    </div>
                </div>
                <div class="bg-white p-4 rounded-lg shadow-md">
                    <div class="chart-container">
                        <canvas id="chart-gastos"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sección Ventas -->
        <div class="mb-10">
            <h2 class="text-2xl font-bold mb-5">Ventas</h2>
            <div class="bg-white p-6 rounded-lg shadow-md">
                <table class="min-w-full bg-white datatable">
                    <thead>
                        <tr>
                            <th class="py-2">Sucursal</th>
                            <th class="py-2">Monto</th>
                            <th class="py-2">Descripción</th>
                            <th class="py-2">Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ventas as $rubro => $items): ?>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td class="border px-4 py-2"><?php echo $item['sucursal']; ?></td>
                                    <td class="border px-4 py-2"><?php echo number_format($item['monto'], 0, ',', '.'); ?> CLP</td>
                                    <td class="border px-4 py-2"><?php echo $item['descripcion']; ?></td>
                                    <td class="border px-4 py-2"><?php echo date('d/m/Y', strtotime($item['fecha'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Sección Retiros -->
        <div class="mb-10">
            <h2 class="text-2xl font-bold mb-5">Retiros</h2>
            <div class="bg-white p-6 rounded-lg shadow-md">
                <table class="min-w-full bg-white datatable">
                    <thead>
                        <tr>
                            <th class="py-2">Sucursal</th>
                            <th class="py-2">Monto</th>
                            <th class="py-2">Descripción</th>
                            <th class="py-2">Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($retiros as $rubro => $items): ?>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td class="border px-4 py-2"><?php echo $item['sucursal']; ?></td>
                                    <td class="border px-4 py-2"><?php echo number_format($item['monto'], 0, ',', '.'); ?> CLP</td>
                                    <td class="border px-4 py-2"><?php echo $item['descripcion']; ?></td>
                                    <td class="border px-4 py-2"><?php echo date('d/m/Y', strtotime($item['fecha'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Sección Gastos -->
        <div class="mb-10">
            <h2 class="text-2xl font-bold mb-5">Gastos</h2>
            <div class="bg-white p-6 rounded-lg shadow-md">
                <table class="min-w-full bg-white datatable">
                    <thead>
                        <tr>
                            <th class="py-2">Sucursal</th>
                            <th class="py-2">Monto</th>
                            <th class="py-2">Descripción</th>
                            <th class="py-2">Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($gastos as $rubro => $items): ?>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td class="border px-4 py-2"><?php echo $item['sucursal']; ?></td>
                                    <td class="border px-4 py-2"><?php echo number_format($item['monto'], 0, ',', '.'); ?> CLP</td>
                                    <td class="border px-4 py-2"><?php echo $item['descripcion']; ?></td>
                                    <td class="border px-4 py-2"><?php echo date('d/m/Y', strtotime($item['fecha'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Sección Sueldos -->
        <div class="mb-10">
            <h2 class="text-2xl font-bold mb-5">Sueldos</h2>
            <div class="bg-white p-6 rounded-lg shadow-md">
                <table class="min-w-full bg-white datatable">
                    <thead>
                        <tr>
                            <th class="py-2">Usuario</th>
                            <th class="py-2">Monto</th>
                            <th class="py-2">Fecha de Pago</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sueldos as $item): ?>
                            <tr>
                                <td class="border px-4 py-2"><?php echo $item['usuario']; ?></td>
                                <td class="border px-4 py-2"><?php echo number_format($item['monto'], 0, ',', '.'); ?> CLP</td>
                                <td class="border px-4 py-2"><?php echo date('d/m/Y', strtotime($item['fecha_pago'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <script>
            $(document).ready(function() {
                $('.datatable').DataTable({
                    language: {
                        url: '//cdn.datatables.net/plug-ins/1.10.24/i18n/Spanish.json'
                    }
                });

                // Configuración común para todos los gráficos
                const commonOptions = {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let value = context.raw;
                                    return new Intl.NumberFormat('es-CL', { 
                                        style: 'currency', 
                                        currency: 'CLP' 
                                    }).format(value);
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return new Intl.NumberFormat('es-CL', { 
                                        style: 'currency', 
                                        currency: 'CLP',
                                        maximumFractionDigits: 0
                                    }).format(value);
                                }
                            }
                        }
                    }
                };

                function crearGrafico(idCanvas, label, data, color) {
                    var ctx = document.getElementById(idCanvas).getContext('2d');
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: Object.keys(data),
                            datasets: [{
                                label: label,
                                data: Object.values(data),
                                backgroundColor: color,
                                borderColor: color,
                                borderWidth: 1
                            }]
                        },
                        options: commonOptions
                    });
                }

                // Datos para los gráficos
                var datosVentas = {
                    <?php foreach ($ventas as $rubro => $items): ?>
                        "<?php echo $rubro; ?>": <?php echo array_sum(array_column($items, 'monto')); ?>,
                    <?php endforeach; ?>
                };

                var datosRetiros = {
                    <?php foreach ($retiros as $rubro => $items): ?>
                        "<?php echo $rubro; ?>": <?php echo array_sum(array_column($items, 'monto')); ?>,
                    <?php endforeach; ?>
                };

                var datosGastos = {
                    <?php foreach ($gastos as $rubro => $items): ?>
                        "<?php echo $rubro; ?>": <?php echo array_sum(array_column($items, 'monto')); ?>,
                    <?php endforeach; ?>
                };

                crearGrafico('chart-ventas', 'Ventas por Rubro', datosVentas, 'rgba(75, 192, 192, 0.7)');
                crearGrafico('chart-retiros', 'Retiros por Rubro', datosRetiros, 'rgba(255, 99, 132, 0.7)');
                crearGrafico('chart-gastos', 'Gastos por Rubro', datosGastos, 'rgba(255, 159, 64, 0.7)');
            });
        </script>
    </div>
</body>
</html>
