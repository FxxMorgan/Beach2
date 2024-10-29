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

// Verificar si el usuario tiene permiso para acceder a Gastos
if (!in_array('gastos', $permisos_usuario)) {
    echo "No tienes permiso para acceder a esta página.";
    exit();
}

// Variables para los filtros
$time_range = $_GET['time_range'] ?? 'month';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// Construir la consulta SQL según el filtro de tiempo
$where_clause = "WHERE sucursal_id = ?";
if ($time_range == 'day') {
    $where_clause .= " AND DATE(fecha) = CURDATE()";
} elseif ($time_range == 'month') {
    $where_clause .= " AND MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE())";
} elseif ($time_range == 'year') {
    $where_clause .= " AND YEAR(fecha) = YEAR(CURDATE())";
} elseif ($time_range == 'custom' && $start_date && $end_date) {
    $where_clause .= " AND DATE(fecha) BETWEEN '$start_date' AND '$end_date'";
}

// Obtener datos de gastos para el DataTable y el gráfico
$sql = "SELECT id, monto, descripcion, fecha FROM gastos $where_clause";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $sucursal_id);
$stmt->execute();
$result = $stmt->get_result();

$gastos = [];
while ($row = $result->fetch_assoc()) {
    $gastos[] = $row;
}

$gastos_labels = array_column($gastos, 'fecha');
$gastos_data = array_column($gastos, 'monto');

// Obtener nombre de la sucursal
$sucursal_nombre = "Sucursal"; // Esto debería obtenerse de la base de datos

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ajax'])) {
    // Lógica para devolver los datos filtrados en formato JSON
    echo json_encode([
        'labels' => $gastos_labels,
        'data' => $gastos_data
    ]);
    exit();
}

// Cerrar conexión
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Gastos</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.dataTables.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

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
            width: 100%;
        }
    </style>
</head>
<body class="bg-gray-100">
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
        <main class="content flex-grow p-8">
            <h1 class="text-3xl font-bold text-center mb-5">Gastos - Sucursal: <?php echo $sucursal_nombre; ?></h1>

            <!-- FORMULARIO DE FILTRO (Rango de tiempo) -->
            <form method="GET" id="filterForm" class="mb-6 space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label for="time_range" class="block text-lg font-semibold mb-2">Seleccionar Rango de Tiempo:</label>
                        <select name="time_range" id="time_range" class="border p-2 rounded-md w-full">
                            <option value="day" <?php if ($time_range == 'day') echo 'selected'; ?>>Diario</option>
                            <option value="month" <?php if ($time_range == 'month') echo 'selected'; ?>>Mensual</option>
                            <option value="year" <?php if ($time_range == 'year') echo 'selected'; ?>>Anual</option>
                            <option value="custom" <?php if ($time_range == 'custom') echo 'selected'; ?>>Personalizado</option>
                        </select>
                    </div>

                    <div id="customDates" class="grid grid-cols-1 sm:grid-cols-2 gap-4" style="display: <?php echo ($time_range == 'custom') ? 'block' : 'none'; ?>;">
                        <div>
                            <label for="start_date" class="block text-lg font-semibold mb-2">Fecha Inicio:</label>
                            <input type="date" name="start_date" value="<?php echo $start_date; ?>" class="border p-2 rounded-md w-full">
                        </div>
                        <div>
                            <label for="end_date" class="block text-lg font-semibold mb-2">Fecha Fin:</label>
                            <input type="date" name="end_date" value="<?php echo $end_date; ?>" class="border p-2 rounded-md w-full">
                        </div>
                    </div>
                </div>

                <button type="submit" class="bg-indigo-600 text-white p-3 rounded-lg font-bold hover:bg-indigo-700 w-auto">Filtrar</button>
            </form>

            <!-- FORMULARIO DE REGISTRO DE GASTOS (método POST) -->
            <div class="max-w-4xl mx-auto bg-white p-10 rounded-lg shadow-md">
                <form method="POST" class="mb-6">
                    <div class="mb-4">
                        <label for="gasto_tipo" class="block text-gray-700 font-bold mb-2">Tipo de Gasto</label>
                        <select id="gasto_tipo" name="gasto_tipo" required class="w-full p-3 border rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="fijo">Gasto Fijo</option>
                            <option value="variable">Gasto Variable</option>
                        </select>
                    </div>
                    <div class="mb-4" id="description_container">
                        <label for="tipo" class="block text-gray-700 font-bold mb-2">Descripción del Gasto</label>
                        <select id="tipo" name="tipo" required class="w-full p-3 border rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="Internet">Internet</option>
                            <option value="Electricidad">Electricidad</option>
                            <option value="Agua">Agua</option>
                            <option value="Gas">Gas</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label for="monto" class="block text-gray-700 font-bold mb-2">Monto</label>
                        <input type="text" id="monto" name="monto" required class="w-full p-3 border rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="275.100" oninput="this.value = this.value.replace(/[^0-9,]/g, '');">
                    </div>
                    <button type="submit" class="w-full bg-indigo-600 text-white p-3 rounded-lg font-bold hover:bg-indigo-700">Agregar Gasto</button>
                </form>

                <div class="chart-container mx-auto mb-6">
                    <canvas id="gastosChart"></canvas>
                </div>

                <table id="gastosTable" class="display responsive nowrap" style="width:100%">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Descripción</th>
                            <th>Monto</th>
                            <th>Fecha y Hora</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($gastos as $gasto): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($gasto['id']); ?></td>
                                <td><?php echo htmlspecialchars($gasto['descripcion']); ?></td>
                                <td><?php echo "$" . number_format($gasto['monto'], 0, ',', '.'); ?> CLP</td>
                                <td><?php echo date('d-m-Y H:i:s', strtotime($gasto['fecha'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="mt-6">
                    <a href="dashboard.php" class="bg-gray-600 text-white p-3 rounded-lg font-bold hover:bg-gray-700 inline-block text-center">Volver al Dashboard</a>
                </div>
            </div>
        </main>
    </div>

    <script>
$(document).ready(function() {
    // Inicializar DataTables
    $('#gastosTable').DataTable({
        responsive: true
    });

    // Función para crear el gráfico
    function crearGrafico(labels, data) {
        var ctxGastos = document.getElementById('gastosChart').getContext('2d');
        return new Chart(ctxGastos, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Gastos',
                    data: data,
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            unit: 'month', // Cambia a 'year' si es necesario
                            tooltipFormat: 'MMM yyyy'
                        }
                    },
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    // Crear el gráfico inicial
    var gastosChart = crearGrafico(<?php echo json_encode($gastos_labels); ?>, <?php echo json_encode($gastos_data); ?>);
    
     // Mostrar/ocultar inputs de fecha según el rango de tiempo
    $('#time_range').change(function() {
        if ($(this).val() === 'custom') {
            $('#customDates').show();
        } else {
            $('#customDates').hide();
        }
    });

        // Cambiar formulario según tipo de gasto
        $('#gasto_tipo').change(function() {
            var tipoGasto = $(this).val();
            if (tipoGasto === 'variable') {
                $('#description_container').html(`
                    <label for="descripcion" class="block text-gray-700 font-bold mb-2">Descripción del Gasto</label>
                    <input type="text" id="descripcion" name="descripcion" required class="w-full p-3 border rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Descripción del gasto">
                `);
            } else {
                $('#description_container').html(`
                    <label for="tipo" class="block text-gray-700 font-bold mb-2">Descripción del Gasto</label>
                    <select id="tipo" name="tipo" required class="w-full p-3 border rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="Internet">Internet</option>
                        <option value="Electricidad">Electricidad</option>
                        <option value="Agua">Agua</option>
                        <option value="Gas">Gas</option>
                    </select>
                `);
            }
        });

// Actualizar el gráfico al enviar el formulario
$('#filterForm').submit(function(event) {
        event.preventDefault();
        $.ajax({
            url: window.location.href,
            type: 'GET',
            data: $(this).serialize(),
            success: function(response) {
                // Suponiendo que response contiene los nuevos labels y data
                var newLabels = response.labels; // Asegúrate de que el backend devuelva esto
                var newData = response.data; // Asegúrate de que el backend devuelva esto
                
                // Destruir el gráfico anterior
                gastosChart.destroy();

                // Crear un nuevo gráfico con los datos actualizados
                gastosChart = crearGrafico(newLabels, newData);
            }
        });
    });
});
</script>
</body>
</html>
