<?php
// Conexión a la base de datos
$conn = new mysqli('localhost', 'root', '', 'beach2');

if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

date_default_timezone_set('America/Santiago');

$fecha_ayer = date('Y-m-d', strtotime('-1 day'));

function obtenerDatosPorSucursal($conn, $tabla, $sucursal_id_col, $fecha_col) {
    // Determinamos los campos adicionales según la tabla
    $campos_adicionales = "";
    
    if ($tabla === 'cheques') {
        $campos_adicionales = ", $tabla.estado";
    } elseif ($tabla === 'gastos') {
        $campos_adicionales = ", $tabla.gasto_tipo";
    }

    // Construimos la consulta con los campos adicionales si están definidos
    $sql = "SELECT sucursales.rubro, CONCAT(sucursales.nombre, ' - ', sucursales.direccion) AS sucursal, 
            $tabla.monto, $tabla.descripcion, $tabla.$fecha_col AS fecha $campos_adicionales
            FROM $tabla 
            JOIN sucursales ON $tabla.$sucursal_id_col = sucursales.id
            ORDER BY $tabla.$fecha_col DESC";

    $result = $conn->query($sql);
    $data = [];

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $data[$row['rubro']][] = $row;
        }
    }

    return $data;
}

// Llamadas para obtener cada tipo de datos
$ventas = obtenerDatosPorSucursal($conn, 'ventas', 'sucursal_id', 'fecha');
$cheques = obtenerDatosPorSucursal($conn, 'cheques', 'sucursal_id', 'fecha_pago');
$gastos = obtenerDatosPorSucursal($conn, 'gastos', 'sucursal_id', 'fecha');

// Obtener datos de sueldos
$sql_sueldos = "SELECT 
    u.nombre AS usuario_pago,
    t.nombre AS trabajador,
    s.monto,
    s.fecha_pago,
    CONCAT(suc.nombre, ' - ', suc.direccion) AS sucursal,
    suc.rubro
FROM sueldos s
INNER JOIN usuarios u ON s.usuario_id = u.id  
INNER JOIN trabajadores t ON s.trabajador_id = t.id  
INNER JOIN sucursales suc ON t.sucursal_id = suc.id  
ORDER BY s.fecha_pago DESC";

$result_sueldos = $conn->query($sql_sueldos);

if (!$result_sueldos) {
    echo "Error en la consulta de sueldos: " . $conn->error;
}

$sueldos = [];
if ($result_sueldos && $result_sueldos->num_rows > 0) {
    while ($row = $result_sueldos->fetch_assoc()) {
        $sueldos[] = $row;
    }
}

// Obtener gastos por departamento
function obtenerGastosPorDepartamento($conn, $fecha_ayer) {
    $sql = "SELECT d.nombre AS departamento, SUM(g.monto) AS total_gastos 
            FROM gastos g 
            JOIN departamentos d ON g.departamento_id = d.id 
            WHERE DATE(g.fecha) = '$fecha_ayer' 
            GROUP BY d.nombre";
    $result = $conn->query($sql);

    $data = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    return $data;
}

$gastos_por_departamento = obtenerGastosPorDepartamento($conn, $fecha_ayer);

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard de Finanzas</title>
    <link rel="icon" href="/images/favicon.ico" type="image/x-icon">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css" rel="stylesheet">
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
        
        <!-- Fecha de visualización -->
        <div class="text-center mb-5">
            <h3 class="text-xl">Mostrando datos del: <?php echo date('d/m/Y', strtotime($fecha_ayer)); ?></h3>
        </div>

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
                        <canvas id="chart-cheques"></canvas> <!-- Cambiado a 'chart-cheques' -->
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
                <?php if (empty($ventas)): ?>
                    <div class="text-center text-gray-500 py-4">
                        No se encontraron ventas para el día <?php echo date('d/m/Y', strtotime($fecha_ayer)); ?>
                    </div>
                <?php else: ?>
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
                                        <td class="border px-4 py-2"><?php echo htmlspecialchars($item['sucursal']); ?></td>
                                        <td class="border px-4 py-2"><?php echo number_format($item['monto'], 0, ',', '.'); ?> CLP</td>
                                        <td class="border px-4 py-2"><?php echo htmlspecialchars($item['descripcion']); ?></td>
                                        <td class="border px-4 py-2"><?php echo date('d/m/Y', strtotime($item['fecha'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
<!-- Sección Cheques -->
<div class="mb-10">
    <h2 class="text-2xl font-bold mb-5">Cheques</h2>
    <div class="bg-white p-6 rounded-lg shadow-md">
        <?php if (empty($cheques)): ?>
            <div class="text-center text-gray-500 py-4">
                No se encontraron cheques para el día <?php echo date('d/m/Y', strtotime($fecha_ayer)); ?>
            </div>
        <?php else: ?>
            <table class="min-w-full bg-white datatable">
                <thead>
                    <tr>
                        <th class="py-2">Sucursal</th>
                        <th class="py-2">Monto</th>
                        <th class="py-2">Descripción</th>
                        <th class="py-2">Fecha de Pago</th>
                        <th class="py-2">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cheques as $rubro => $items): ?>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td class="border px-4 py-2"><?php echo htmlspecialchars($item['sucursal']); ?></td>
                                <td class="border px-4 py-2"><?php echo number_format($item['monto'], 2, ',', '.'); ?> CLP</td>
                                <td class="border px-4 py-2"><?php echo htmlspecialchars($item['descripcion']); ?></td>
                                <td class="border px-4 py-2"><?php echo date('d/m/Y', strtotime($item['fecha_pago'])); ?></td>
                                <td class="border px-4 py-2"><?php echo htmlspecialchars($item['estado']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

        <!-- Sección Gastos -->
        <div class="mb-10">
            <h2 class="text-2xl font-bold mb-5">Gastos</h2>
            <div class="bg-white p-6 rounded-lg shadow-md">
                <?php if (empty($gastos)): ?>
                    <div class="text-center text-gray-500 py-4">
                        No se encontraron gastos para el día <?php echo date('d/m/Y', strtotime($fecha_ayer)); ?>
                    </div>
                <?php else: ?>
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
                                        <td class="border px-4 py-2"><?php echo htmlspecialchars($item['sucursal']); ?></td>
                                        <td class="border px-4 py-2"><?php echo number_format($item['monto'], 0, ',', '.'); ?> CLP</td>
                                        <td class="border px-4 py-2"><?php echo htmlspecialchars($item['descripcion']); ?></td>
                                        <td class="border px-4 py-2"><?php echo date('d/m/Y', strtotime($item['fecha'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sección Gastos por Departamento -->
        <div class="mb-10">
            <h2 class="text-2xl font-bold mb-5">Gastos por Departamento</h2>
            <div class="bg-white p-6 rounded-lg shadow-md">
                <?php if (empty($gastos_por_departamento)): ?>
                    <div class="text-center text-gray-500 py-4">
                        No se encontraron gastos por departamento para el día <?php echo date('d/m/Y', strtotime($fecha_ayer)); ?>
                    </div>
                <?php else: ?>
                    <table class="min-w-full bg-white datatable">
                        <thead>
                            <tr>
                                <th class="py-2">Departamento</th>
                                <th class="py-2">Total Gastos</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($gastos_por_departamento as $item): ?>
                                <tr>
                                    <td class="border px-4 py-2"><?php echo htmlspecialchars($item['departamento']); ?></td>
                                    <td class="border px-4 py-2"><?php echo number_format($item['total_gastos'], 0, ',', '.'); ?> CLP</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sección Sueldos -->
        <div class="mb-10">
            <h2 class="text-2xl font-bold mb-5">Sueldos</h2>
            <div class="bg-white p-6 rounded-lg shadow-md">
                <?php if (empty($sueldos)): ?>
                    <div class="text-center text-gray-500 py-4">
                        No se encontraron registros de sueldos
                    </div>
                <?php else: ?>
                    <table class="min-w-full bg-white datatable">
                        <thead>
                            <tr>
                                <th class="py-2">Pagado por</th>
                                <th class="py-2">Trabajador</th>
                                <th class="py-2">Sucursal</th>
                                <th class="py-2">Rubro</th>
                                <th class="py-2">Monto</th>
                                <th class="py-2">Fecha de Pago</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sueldos as $item): ?>
                                <tr>
                                    <td class="border px-4 py-2"><?php echo htmlspecialchars($item['usuario_pago']); ?></td>
                                    <td class="border px-4 py-2"><?php echo htmlspecialchars($item['trabajador']); ?></td>
                                    <td class="border px-4 py-2"><?php echo htmlspecialchars($item['sucursal']); ?></td>
                                    <td class="border px-4 py-2"><?php echo htmlspecialchars($item['rubro']); ?></td>
                                    <td class="border px-4 py-2"><?php echo number_format($item['monto'], 0, ',', '.'); ?> CLP</td>
                                    <td class="border px-4 py-2"><?php echo date('d/m/Y', strtotime($item['fecha_pago'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <script>

            
$(document).ready(function() {
    // Set default Chart.js configuration
    Chart.defaults.font.family = 'Arial';
    Chart.defaults.font.size = 12;

    // Common options for all charts
    const commonOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true,
                position: 'top',
                labels: {
                    padding: 20,
                    boxWidth: 15
                }
            },
            tooltip: {
                enabled: true,
                mode: 'index',
                intersect: false,
                callbacks: {
                    label: function(context) {
                        let value = context.raw || 0;
                        return context.dataset.label + ': ' + new Intl.NumberFormat('es-CL', { 
                            style: 'currency', 
                            currency: 'CLP',
                            maximumFractionDigits: 0
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
                            maximumFractionDigits: 0,
                            notation: 'compact'
                        }).format(value);
                    }
                }
            },
            x: {
                ticks: {
                    maxRotation: 45,
                    minRotation: 45
                }
            }
        }
    };

    // Function to safely calculate totals by rubro
    function calcularTotalesPorRubro(datos) {
        console.log('Datos recibidos:', datos); // Debug log
        
        if (!datos || typeof datos !== 'object') {
            console.warn('Datos inválidos:', datos);
            return {};
        }

        try {
            return Object.entries(datos).reduce((acc, [rubro, items]) => {
                if (Array.isArray(items)) {
                    acc[rubro] = items.reduce((sum, item) => {
                        const monto = parseFloat(item.monto) || 0;
                        return sum + monto;
                    }, 0);
                }
                return acc;
            }, {});
        } catch (error) {
            console.error('Error al calcular totales:', error);
            return {};
        }
    }

    // Function to create or update a chart
    function crearGrafico(idCanvas, label, data, color) {
        const canvas = document.getElementById(idCanvas);
        if (!canvas) {
            console.error(`Canvas no encontrado: ${idCanvas}`);
            return;
        }

        // Get the container dimensions
        const container = canvas.parentElement;
        canvas.style.width = '100%';
        canvas.style.height = '300px';

        const ctx = canvas.getContext('2d');
        
        // Destroy existing chart if it exists
        if (window[`chart_${idCanvas}`]) {
            window[`chart_${idCanvas}`].destroy();
        }

        if (!data || Object.keys(data).length === 0) {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillStyle = '#666666';
            ctx.font = '14px Arial';
            ctx.fillText('No hay datos disponibles', canvas.width / 2, canvas.height / 2);
            return;
        }

        // Create new chart
        window[`chart_${idCanvas}`] = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: Object.keys(data),
                datasets: [{
                    label: label,
                    data: Object.values(data),
                    backgroundColor: color,
                    borderColor: color,
                    borderWidth: 1,
                    borderRadius: 5,
                    barThickness: 'flex'
                }]
            },
            options: commonOptions
        });
    }

    // Initialize the charts with error handling
    try {
        console.log('Inicializando gráficos...'); // Debug log

        // Get and process data
        const ventas = <?php echo json_encode($ventas, JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE) ?: '{}'; ?>;
        const cheques = <?php echo json_encode($cheques, JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE) ?: '{}'; ?>;
        const gastos = <?php echo json_encode($gastos, JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE) ?: '{}'; ?>;

        console.log('Datos cargados:', { ventas, cheques, gastos }); // Debug log

        // Calculate totals
        const datosVentas = calcularTotalesPorRubro(ventas);
        const datosCheques = calcularTotalesPorRubro(cheques);
        const datosGastos = calcularTotalesPorRubro(gastos);

        console.log('Totales calculados:', {
            ventas: datosVentas,
            cheques: datosCheques,
            gastos: datosGastos
        }); // Debug log

        // Create charts
        crearGrafico('chart-ventas', 'Ventas', datosVentas, 'rgba(75, 192, 192, 0.8)');
        crearGrafico('chart-cheques', 'Cheques', datosCheques, 'rgba(255, 99, 132, 0.8)');
        crearGrafico('chart-gastos', 'Gastos', datosGastos, 'rgba(255, 206, 86, 0.8)');

    } catch (error) {
        console.error('Error al inicializar los gráficos:', error);
    }
});

// Initialize DataTables
$('.datatable').DataTable({
    "language": {
        "url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/Spanish.json"
    }
});
</script>

        <script>

            
$(document).ready(function() {
    // Set default Chart.js configuration
    Chart.defaults.font.family = 'Arial';
    Chart.defaults.font.size = 12;

    // Common options for all charts
    const commonOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true,
                position: 'top',
                labels: {
                    padding: 20,
                    boxWidth: 15
                }
            },
            tooltip: {
                enabled: true,
                mode: 'index',
                intersect: false,
                callbacks: {
                    label: function(context) {
                        let value = context.raw || 0;
                        return context.dataset.label + ': ' + new Intl.NumberFormat('es-CL', { 
                            style: 'currency', 
                            currency: 'CLP',
                            maximumFractionDigits: 0
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
                            maximumFractionDigits: 0,
                            notation: 'compact'
                        }).format(value);
                    }
                }
            },
            x: {
                ticks: {
                    maxRotation: 45,
                    minRotation: 45
                }
            }
        }
    };

    // Function to safely calculate totals by rubro
    function calcularTotalesPorRubro(datos) {
        console.log('Datos recibidos:', datos); // Debug log
        
        if (!datos || typeof datos !== 'object') {
            console.warn('Datos inválidos:', datos);
            return {};
        }

        try {
            return Object.entries(datos).reduce((acc, [rubro, items]) => {
                if (Array.isArray(items)) {
                    acc[rubro] = items.reduce((sum, item) => {
                        const monto = parseFloat(item.monto) || 0;
                        return sum + monto;
                    }, 0);
                }
                return acc;
            }, {});
        } catch (error) {
            console.error('Error al calcular totales:', error);
            return {};
        }
    }

    // Function to create or update a chart
    function crearGrafico(idCanvas, label, data, color) {
        const canvas = document.getElementById(idCanvas);
        if (!canvas) {
            console.error(`Canvas no encontrado: ${idCanvas}`);
            return;
        }

        // Get the container dimensions
        const container = canvas.parentElement;
        canvas.style.width = '100%';
        canvas.style.height = '300px';

        const ctx = canvas.getContext('2d');
        
        // Destroy existing chart if it exists
        if (window[`chart_${idCanvas}`]) {
            window[`chart_${idCanvas}`].destroy();
        }

        if (!data || Object.keys(data).length === 0) {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillStyle = '#666666';
            ctx.font = '14px Arial';
            ctx.fillText('No hay datos disponibles', canvas.width / 2, canvas.height / 2);
            return;
        }

        // Create new chart
        window[`chart_${idCanvas}`] = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: Object.keys(data),
                datasets: [{
                    label: label,
                    data: Object.values(data),
                    backgroundColor: color,
                    borderColor: color,
                    borderWidth: 1,
                    borderRadius: 5,
                    barThickness: 'flex'
                }]
            },
            options: commonOptions
        });
    }

    // Initialize the charts with error handling
    try {
        console.log('Inicializando gráficos...'); // Debug log

        // Get and process data
        const ventas = <?php echo json_encode($ventas, JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE) ?: '{}'; ?>;
        const cheques = <?php echo json_encode($cheques, JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE) ?: '{}'; ?>;
        const gastos = <?php echo json_encode($gastos, JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE) ?: '{}'; ?>;

        console.log('Datos cargados:', { ventas, cheques, gastos }); // Debug log

        // Calculate totals
        const datosVentas = calcularTotalesPorRubro(ventas);
        const datosCheques = calcularTotalesPorRubro(cheques);
        const datosGastos = calcularTotalesPorRubro(gastos);

        console.log('Totales calculados:', {
            ventas: datosVentas,
            cheques: datosCheques,
            gastos: datosGastos
        }); // Debug log

        // Create charts
        crearGrafico('chart-ventas', 'Ventas', datosVentas, 'rgba(75, 192, 192, 0.8)');
        crearGrafico('chart-cheques', 'Cheques', datosCheques, 'rgba(255, 99, 132, 0.8)');
        crearGrafico('chart-gastos', 'Gastos', datosGastos, 'rgba(255, 206, 86, 0.8)');

    } catch (error) {
        console.error('Error al inicializar los gráficos:', error);
    }
});
</script>

</body>
</html>