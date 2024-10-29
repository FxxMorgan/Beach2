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
    if ($rol_id == 2) {
        // Si el rol es 2, tiene acceso a todo
        return ['dashboard', 'ventas', 'usuarios', 'gastos', 'sucursales'];
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

// Cerrar la conexión
$conn->close();

// Verificar si el usuario tiene permiso para acceder al Dashboard
if (!in_array('dashboard', $permisos_usuario)) {
    echo "No tienes permiso para acceder a esta página.";
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>

    <!-- Favicon -->
    <link rel="icon" href="../../favicon.ico" type="image/x-icon">

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
                        <a id="dashboard-link" href="index.php" class="flex items-center text-white hover:bg-blue-600 p-2 rounded">
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
                    <li class="mb-4">
                        <a id="sucursales-link" href="sucursales.php" class="flex items-center text-white hover:bg-blue-600 p-2 rounded">
                            <span class="material-icons mr-2">store</span>
                            Sucursales
                        </a>
                    </li>
                    <?php if (in_array('usuarios', $permisos_usuario)): ?>
                    <li class="mb-4">
                        <a id="usuarios-link" href="usuarios.php" class="flex items-center text-white hover:bg-blue-600 p-2 rounded">
                            <span class="material-icons mr-2">people</span>
                            Usuarios
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
                        <!-- Aquí puedes agregar dinámicamente los datos desde PHP -->
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

            // Inicializar Chart.js para Ventas
            var ventasCtx = document.getElementById('ventasChart').getContext('2d');
            var ventasChart = new Chart(ventasCtx, {
                type: 'bar',
                data: {
                    labels: ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio'],
                    datasets: [{
                        label: 'Ventas',
                        data: [12000, 15000, 8000, 14000, 13000, 17000],
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
                    labels: ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio'],
                    datasets: [{
                        label: 'Gastos',
                        data: [5000, 7000, 6000, 8000, 7500, 9000],
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
