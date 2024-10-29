<?php
session_start();

// Configurar la zona horaria a Santiago
date_default_timezone_set('America/Santiago');

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

// Procesar formulario de ventas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tipo_formulario']) && $_POST['tipo_formulario'] === 'venta') {
    $descripcion = $_POST['descripcion'];
    $monto = $_POST['monto'];
    $fecha = date('Y-m-d H:i:s');

    $sql = "INSERT INTO ventas (descripcion, monto, sucursal_id, fecha) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("siis", $descripcion, $monto, $sucursal_id, $fecha);
        $stmt->execute();
        echo "Venta registrada con éxito.";
    } else {
        echo "Error al preparar la consulta: " . $conn->error;
    }
}

// Procesar formulario de retiros
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tipo_formulario']) && $_POST['tipo_formulario'] === 'retiro') {
    $motivo = $_POST['motivo'];
    $monto = $_POST['monto'];
    $fecha = date('Y-m-d H:i:s');

    $sql = "INSERT INTO retiros (descripcion, monto, sucursal_id, fecha) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("siis", $motivo, $monto, $sucursal_id, $fecha);
        $stmt->execute();
        echo "Retiro registrado con éxito.";
    } else {
        echo "Error al preparar la consulta: " . $conn->error;
    }
}

// Obtener datos de ventas y retiros para mostrar
$ventas = [];
$retiros = [];

$sql_ventas = "SELECT fecha, descripcion, monto FROM ventas WHERE sucursal_id = ?";
$stmt_ventas = $conn->prepare($sql_ventas);
if ($stmt_ventas) {
    $stmt_ventas->bind_param("i", $sucursal_id);
    $stmt_ventas->execute();
    $result_ventas = $stmt_ventas->get_result();
    while ($row = $result_ventas->fetch_assoc()) {
        $ventas[] = $row;
    }
}

$sql_retiros = "SELECT fecha, descripcion AS motivo, monto FROM retiros WHERE sucursal_id = ?";
$stmt_retiros = $conn->prepare($sql_retiros);
if ($stmt_retiros) {
    $stmt_retiros->bind_param("i", $sucursal_id);
    $stmt_retiros->execute();
    $result_retiros = $stmt_retiros->get_result();
    while ($row = $result_retiros->fetch_assoc()) {
        $retiros[] = $row;
    }
}

// Cerrar conexión
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ventas y Retiros</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns"></script>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

    <style>
        body {
            font-family: 'Roboto', sans-serif;
        }
        .sidebar {
            background-color: #1a202c;
            color: white;
        }
        .sidebar a {
            color: white;
        }
    </style>
</head>

<body class="bg-gray-100">
    <div class="flex">
        <!-- Sidebar -->
        <aside class="sidebar h-screen w-64 p-4">
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
                        <a id="gastos-link" href="gastos.php" class="flex items-center text-white bg-blue-600 p-2 rounded">
                            <span class="material-icons mr-2">monetization_on</span>
                            Gastos
                        </a>
                    </li>
                    <li class="mb-4">
                        <a id="ventas-link" href="ventas.php" class="flex items-center text-white hover:bg-blue-600 p-2 rounded">
                            <span class="material-icons mr-2">shopping_cart</span>
                            Ventas
                        </a>
                    </li>
                    <li class="mb-4">
                        <a id="sucursales-link" href="sucursales.php" class="flex items-center text-white hover:bg-blue-600 p-2 rounded">
                            <span class="material-icons mr-2">store</span>
                            Sucursales
                        </a>
                    </li>
                    <li class="mb-4">
                        <a id="usuarios-link" href="usuarios.php" class="flex items-center text-white hover:bg-blue-600 p-2 rounded">
                            <span class="material-icons mr-2">people</span>
                            Usuarios
                        </a>
                    </li>
                    <li class="mb-4">
                        <a id="salario-link" href="salario.php" class="flex items-center text-white hover:bg-blue-600 p-2 rounded">
                            <span class="material-icons mr-2">attach_money</span>
                            Salario
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-8">
            <h1 class="text-3xl font-bold mb-8">Ventas y Retiros</h1>

            <!-- Formulario para Ingresar Ventas Diarias -->
            <div class="bg-white p-6 rounded shadow mb-8">
                <h2 class="text-xl font-semibold mb-4">Registrar Venta</h2>
                <form action="ventas.php" method="post">
                    <input type="hidden" name="tipo_formulario" value="venta">
                    <label for="descripcion" class="block mb-2">Descripción:</label>
                    <input type="text" id="descripcion" name="descripcion" class="w-full mb-4 p-2 border" required>

                    <label for="monto" class="block mb-2">Monto (CLP):</label>
                    <input type="number" id="monto" name="monto" class="w-full mb-4 p-2 border" required>

                    <button type="submit" class="bg-blue-500 text-white p-2 rounded">Registrar Venta</button>
                </form>
            </div>

            <!-- Formulario para Registrar Retiros de Caja -->
            <div class="bg-white p-6 rounded shadow mb-8">
                <h2 class="text-xl font-semibold mb-4">Registrar Retiro</h2>
                <form action="ventas.php" method="post">
                    <input type="hidden" name="tipo_formulario" value="retiro">
                    <label for="motivo" class="block mb-2">Motivo del Retiro:</label>
                    <select id="motivo" name="motivo" class="w-full mb-4 p-2 border" required>
                        <option value="Pago de Proveedores">Pago de Proveedores</option>
                        <option value="Mantenimiento">Mantenimiento</option>
                        <option value="Otros">Otros</option>
                    </select>

                    <label for="monto" class="block mb-2">Monto (CLP):</label>
                    <input type="number" id="monto" name="monto" class="w-full mb-4 p-2 border" required>

                    <button type="submit" class="bg-blue-500 text-white p-2 rounded">Registrar Retiro</button>
                </form>
            </div>

            <!-- Gráfico de Ventas -->
            <div class="bg-white p-6 rounded shadow mb-8">
                <h2 class="text-xl font-semibold mb-4">Gráfico de Ventas</h2>
                <canvas id="ventasChart"></canvas>
            </div>

            <!-- Gráfico de Retiros -->
            <div class="bg-white p-6 rounded shadow mb-8">
                <h2 class="text-xl font-semibold mb-4">Gráfico de Retiros</h2>
                <canvas id="retirosChart"></canvas>
            </div>

            <!-- Mostrar Ventas Existentes -->
            <div class="bg-white p-6 rounded shadow mb-8">
                <h2 class="text-xl font-semibold mb-4">Historial de Ventas</h2>
                <table id="tablaVentas" class="w-full">
                    <thead>
                        <tr>
                            <th class="text-left">Fecha y Hora</th>
                            <th class="text-left">Descripción</th>
                            <th class="text-left">Monto (CLP)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ventas as $venta): ?>
                            <tr>
                                <td><?php echo date('d-m-Y H:i:s', strtotime($venta['fecha'])); ?></td>
                                <td><?php echo htmlspecialchars($venta['descripcion']); ?></td>
                                <td><?php echo "$" . number_format($venta['monto'], 0, ',', '.'); ?> CLP</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mostrar Retiros Existentes -->
            <div class="bg-white p-6 rounded shadow mb-8">
                <h2 class="text-xl font-semibold mb-4">Historial de Retiros</h2>
                <table id="tablaRetiros" class="w-full">
                    <thead>
                        <tr>
                            <th class="text-left">Fecha y Hora</th>
                            <th class="text-left">Motivo</th>
                            <th class="text-left">Monto (CLP)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($retiros as $retiro): ?>
                            <tr>
                                <td><?php echo date('d-m-Y H:i:s', strtotime($retiro['fecha'])); ?></td>
                                <td><?php echo htmlspecialchars($retiro['motivo']); ?></td>
                                <td><?php echo "$" . number_format($retiro['monto'], 0, ',', '.'); ?> CLP</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <script>
        $(document).ready(function() {
            $('#tablaVentas').DataTable();
            $('#tablaRetiros').DataTable();
        });

        // Datos para el gráfico de ventas
        const ventasLabels = <?php echo json_encode(array_column($ventas, 'fecha')); ?>;
        const ventasData = <?php echo json_encode(array_column($ventas, 'monto')); ?>;

        // Configuración de Chart.js para Ventas
        const ctxVentas = document.getElementById('ventasChart').getContext('2d');
        const ventasChart = new Chart(ctxVentas, {
            type: 'line',
            data: {
                labels: ventasLabels,
                datasets: [{
                    label: 'Ventas (CLP)',
                    data: ventasData,
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            unit: 'day'
                        }
                    },
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Datos para el gráfico de retiros
        const retirosLabels = <?php echo json_encode(array_column($retiros, 'fecha')); ?>;
        const retirosData = <?php echo json_encode(array_column($retiros, 'monto')); ?>;

        // Configuración de Chart.js para Retiros
        const ctxRetiros = document.getElementById('retirosChart').getContext('2d');
        const retirosChart = new Chart(ctxRetiros, {
            type: 'line',
            data: {
                labels: retirosLabels,
                datasets: [{
                    label: 'Retiros (CLP)',
                    data: retirosData,
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            unit: 'day'
                        }
                    },
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>

</html>
