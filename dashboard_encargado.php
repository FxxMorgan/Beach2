<?php
session_start();

// Redirigir si el usuario no ha iniciado sesión
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['sucursal_id']) || !isset($_SESSION['rol_id'])) {
    header('Location: login.php');
    exit();
}

// Conexión a la base de datos
$conn = new mysqli('localhost', 'root', '', 'beach2');

// Verificar la conexión
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

// Obtener los datos de la sesión
$usuario_id = $_SESSION['usuario_id'];
$sucursal_id = $_SESSION['sucursal_id'];
$rol_id = $_SESSION['rol_id'];

// Obtener permisos del usuario
function obtenerPermisos($conn, $usuario_id, $rol_id) {
    if ($rol_id == 4) {
        return ['dashboard', 'ventas', 'gastos'];
    }

    $sql = "SELECT permiso FROM permisos WHERE usuario_id = ? AND estado = 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $permisos = [];
    while ($row = $result->fetch_assoc()) {
        $permisos[] = $row['permiso'];
    }
    return $permisos;
}

$permisos_usuario = obtenerPermisos($conn, $usuario_id, $rol_id);

// Verificar si el usuario tiene permiso para acceder al Dashboard
if (!in_array('dashboard', $permisos_usuario)) {
    echo "No tienes permiso para acceder a esta página.";
    exit();
}

// Obtener datos de ventas mensuales
$ventas_mensuales = [];
$sql_ventas = "SELECT MONTH(fecha) AS mes, SUM(monto) AS total FROM ventas WHERE sucursal_id = ? GROUP BY mes";
$stmt_ventas = $conn->prepare($sql_ventas);
$stmt_ventas->bind_param("i", $sucursal_id);
$stmt_ventas->execute();
$result_ventas = $stmt_ventas->get_result();
while ($row = $result_ventas->fetch_assoc()) {
    $ventas_mensuales[(int)$row['mes']] = (float)$row['total'];
}

// Obtener datos de gastos mensuales
$gastos_mensuales = [];
$sql_gastos = "SELECT MONTH(fecha) AS mes, SUM(monto) AS total FROM gastos WHERE sucursal_id = ? GROUP BY mes";
$stmt_gastos = $conn->prepare($sql_gastos);
$stmt_gastos->bind_param("i", $sucursal_id);
$stmt_gastos->execute();
$result_gastos = $stmt_gastos->get_result();
while ($row = $result_gastos->fetch_assoc()) {
    $gastos_mensuales[(int)$row['mes']] = (float)$row['total'];
}

// Obtener datos de informes
$informes = [];
$sql_informes = "SELECT v.fecha AS fecha_venta, v.monto AS monto_venta, g.monto AS monto_gasto, s.nombre AS sucursal_nombre
                 FROM ventas v
                 LEFT JOIN gastos g ON MONTH(v.fecha) = MONTH(g.fecha) AND v.sucursal_id = g.sucursal_id
                 INNER JOIN sucursales s ON v.sucursal_id = s.id
                 WHERE v.sucursal_id = ? OR g.sucursal_id = ?
                 ORDER BY v.fecha";
$stmt_informes = $conn->prepare($sql_informes);
$stmt_informes->bind_param("ii", $sucursal_id, $sucursal_id);
$stmt_informes->execute();
$result_informes = $stmt_informes->get_result();
while ($row = $result_informes->fetch_assoc()) {
    $informes[] = $row;
}
$stmt_informes->close();

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>

    <!-- Favicon -->
    <link rel="icon" href="images/favicon.ico" type="image/x-icon">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">

    <!-- Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

    <!-- Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">

    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.10.24/css/jquery.dataTables.min.css" rel="stylesheet">

    <!-- Custom CSS -->
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f4f6f9;
        }
        .sidebar {
            background-color: #2c3e50;
            color: #ecf0f1;
        }
        .content {
            margin-left: 250px;
        }
        .card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .chart-container {
            position: relative;
            height: 400px;
        }
    </style>
</head>

<body>
    <div class="flex">
        <!-- Sidebar -->
        <aside class="sidebar fixed h-screen w-64 p-4">
            <div class="mb-8">
                <img src="../../images/logo.png" alt="Logo" class="w-32 mx-auto">
            </div>
            <nav>
                <ul>
                    <li class="mb-4">
                    <a href="<?php echo $dashboard_link; ?>" class="flex items-center text-white hover:bg-blue-600 p-2 rounded">
                            <span class="material-icons mr-2">dashboard</span>
                            Dashboard
                        </a>
                    </li>
                    <li class="mb-4">
                        <a id="gastos-link" href="gastos.php" class="flex items-center text-white hover:bg-blue-600 p-2 rounded">
                            <span class="material-icons mr-2">payments</span>
                            Gastos
                        </a>
                    </li>
                    <?php if (in_array('ventas', $permisos_usuario)): ?>
                    <li class="mb-4">
                        <a id="ventas-link" href="ventas.php" class="flex items-center text-white hover:bg-blue-600 p-2 rounded">
                            <span class="material-icons mr-2">shopping_cart</span>
                            Ventas
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="content flex-grow p-8">
            <h1 class="text-3xl font-bold mb-8">Dashboard</h1>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                <div class="card p-6">
                    <h2 class="text-xl font-semibold mb-4">Ventas</h2>
                    <div class="chart-container">
                        <canvas id="ventasChart"></canvas>
                    </div>
                </div>
                <div class="card p-6">
                    <h2 class="text-xl font-semibold mb-4">Gastos</h2>
                    <div class="chart-container">
                        <canvas id="gastosChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="card p-6">
                <h2 class="text-xl font-semibold mb-4">Informes</h2>
                <table id="informesTable" class="w-full">
                    <thead>
                        <tr>
                            <th class="text-left">Fecha</th>
                            <th class="text-left">Monto Ventas</th>
                            <th class="text-left">Sucursal</th>
                            <th class="text-left">Monto Gastos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($informes as $informe): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($informe['fecha_venta']); ?></td>
                                <td><?php echo htmlspecialchars($informe['monto_venta']); ?></td>
                                <td><?php echo htmlspecialchars($informe['sucursal_nombre']); ?></td>
                                <td><?php echo htmlspecialchars($informe['monto_gasto']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', (event) => {
            // Inicializar DataTables
            $('#informesTable').DataTable();

            // Datos de ventas y gastos desde PHP
            const ventasMensuales = <?php echo json_encode($ventas_mensuales); ?>;
            const gastosMensuales = <?php echo json_encode($gastos_mensuales); ?>;

            // Generar etiquetas y datos para los gráficos
            const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
            const datosVentas = meses.map((mes, index) => ventasMensuales[index + 1] || 0);
            const datosGastos = meses.map((mes, index) => gastosMensuales[index + 1] || 0);

            // Inicializar Chart.js para Ventas
            var ventasCtx = document.getElementById('ventasChart').getContext('2d');
            var ventasChart = new Chart(ventasCtx, {
                type: 'bar',
                data: {
                    labels: meses,
                    datasets: [{
                        label: 'Ventas',
                        data: datosVentas,
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            // Inicializar Chart.js para Gastos
            var gastosCtx = document.getElementById('gastosChart').getContext('2d');
            var gastosChart = new Chart(gastosCtx, {
                type: 'line',
                data: {
                    labels: meses,
                    datasets: [{
                        label: 'Gastos',
                        data: datosGastos,
                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        });
    </script>

</body>
</html>
