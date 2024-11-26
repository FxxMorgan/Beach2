<?php
session_start();

// Verificar sesión
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['sucursal_id']) || !isset($_SESSION['rol_id'])) {
    header('Location: login.php');
    exit();
}

try {
    $conn = new mysqli('localhost', 'root', '', 'beach2');
    if ($conn->connect_error) {
        throw new Exception("Conexión fallida: " . $conn->connect_error);
    }

    date_default_timezone_set('America/Santiago');
    $usuario_id = $_SESSION['usuario_id'];
    $sucursal_id = $_SESSION['sucursal_id'];
    $rol_id = $_SESSION['rol_id'];

    // Procesar el formulario de registro de gastos
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        if (isset($_POST['accion']) && $_POST['accion'] === 'eliminar') {
            $gasto_id = $_POST['gasto_id'];
            $sql = "DELETE FROM gastos WHERE id = ? AND sucursal_id = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("ii", $gasto_id, $sucursal_id);
                if ($stmt->execute()) {
                    echo "<script>alert('Gasto eliminado con éxito');</script>";
                } else {
                    echo "<script>alert('Error al eliminar el gasto: " . $stmt->error . "');</script>";
                }
                $stmt->close();
            } else {
                echo "<script>alert('Error en la preparación de la consulta: " . $conn->error . "');</script>";
            }
        } elseif ($_SERVER["REQUEST_METHOD"] == "POST") {
            if (isset($_POST['accion']) && $_POST['accion'] === 'editar') {
                $gasto_id = $_POST['gasto_id'];
                $monto = $_POST['monto'];
                $descripcion = $_POST['tipo'];
                $gasto_tipo = $_POST['gasto_tipo'];
        
                // Si es un gasto fijo, usamos el valor seleccionado como descripción
                if ($gasto_tipo === 'fijo') {
                    $descripcion = $_POST['gastos-fijos'];
                }
        
                // Preparar la consulta SQL
                $sql = "UPDATE gastos SET monto = ?, descripcion = ?, gasto_tipo = ? WHERE id = ? AND sucursal_id = ?";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("dssii", $monto, $descripcion, $gasto_tipo, $gasto_id, $sucursal_id);
                    if ($stmt->execute()) {
                        echo "<script>alert('Gasto actualizado con éxito');</script>";
                    } else {
                        echo "<script>alert('Error al actualizar el gasto: " . $stmt->error . "');</script>";
                    }
                    $stmt->close();
                } else {
                    echo "<script>alert('Error en la preparación de la consulta: " . $conn->error . "');</script>";
                }
            }
        } else {
            $monto = $_POST['monto'];
            $descripcion = $_POST['tipo']; // Usamos 'tipo' como descripción
            $sucursal_id = $_SESSION['sucursal_id'];
            $gasto_tipo = $_POST['gasto_tipo'];
            $fecha = date('Y-m-d H:i:s');

            // Si es un gasto fijo, usamos el valor seleccionado como descripción
            if ($gasto_tipo === 'fijo') {
                $descripcion = $_POST['gastos-fijos'];
            }

            // Preparar la consulta SQL
            $sql = "INSERT INTO gastos (monto, descripcion, sucursal_id, gasto_tipo, fecha) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);

            if ($stmt) {
                $stmt->bind_param("dsiss", $monto, $descripcion, $sucursal_id, $gasto_tipo, $fecha);
                if ($stmt->execute()) {
                    // Éxito
                    echo "<script>alert('Gasto registrado con éxito');</script>";
                } else {
                    // Error
                    echo "<script>alert('Error al registrar el gasto: " . $stmt->error . "');</script>";
                }
                $stmt->close();
            } else {
                echo "<script>alert('Error en la preparación de la consulta: " . $conn->error . "');</script>";
            }
        }
    }

    // Obtener nombre de la sucursal
    $sql_sucursal = "SELECT nombre FROM sucursales WHERE id = ?";
    $stmt_sucursal = $conn->prepare($sql_sucursal);
    if (!$stmt_sucursal) {
        throw new Exception("Error en la preparación de la consulta de sucursal: " . $conn->error);
    }
    $stmt_sucursal->bind_param("i", $sucursal_id);
    $stmt_sucursal->execute();
    $result_sucursal = $stmt_sucursal->get_result();
    $sucursal_nombre = $result_sucursal->fetch_assoc()['nombre'] ?? 'Desconocida';
    $stmt_sucursal->close();

    // Configuración de filtros
    $time_range = in_array($_GET['time_range'] ?? '', ['day', 'month', 'year', 'custom']) ? $_GET['time_range'] : 'day';
    $gasto_tipo_filtro = in_array($_GET['gasto_tipo_filtro'] ?? '', ['todos', 'fijo', 'variable']) ? $_GET['gasto_tipo_filtro'] : 'todos';
    $start_date = $_GET['start_date'] ?? date('Y-m-d');
    $end_date = $_GET['end_date'] ?? date('Y-m-d');

   // Configuración de $where_clause y $date_format
$where_clause = " WHERE sucursal_id = ?";
$date_format = '%Y-%m-%d'; // Default to daily

if ($time_range === 'custom') {
    $where_clause .= " AND fecha BETWEEN ? AND ?";
} elseif ($time_range === 'month') {
    $date_format = '%Y-%m'; // Monthly
} elseif ($time_range === 'year') {
    $date_format = '%Y'; // Yearly
}

$sql_grafico = "SELECT DATE_FORMAT(fecha, ?) as periodo, SUM(monto) as total, COUNT(*) as cantidad
                FROM gastos" . $where_clause;

if ($gasto_tipo_filtro !== 'todos') {
    $sql_grafico .= " AND gasto_tipo = ?";
}

$sql_grafico .= " GROUP BY periodo ORDER BY MIN(fecha)";

$stmt_grafico = $conn->prepare($sql_grafico);
if (!$stmt_grafico) {
    throw new Exception("Error en la preparación de la consulta del gráfico: " . $conn->error);
}

// Bind de parámetros para gráfico
if ($time_range === 'custom') {
    if ($gasto_tipo_filtro !== 'todos') {
        $stmt_grafico->bind_param('sisss', $date_format, $sucursal_id, $start_date, $end_date, $gasto_tipo_filtro);
    } else {
        $stmt_grafico->bind_param('siss', $date_format, $sucursal_id, $start_date, $end_date);
    }
} else {
    if ($gasto_tipo_filtro !== 'todos') {
        $stmt_grafico->bind_param('sis', $date_format, $sucursal_id, $gasto_tipo_filtro);
    } else {
        $stmt_grafico->bind_param('si', $date_format, $sucursal_id);
    }
}

// Ejecutar consulta del gráfico
if (!$stmt_grafico->execute()) {
    throw new Exception("Error al ejecutar la consulta del gráfico: " . $stmt_grafico->error);
}
$result_grafico = $stmt_grafico->get_result();
$gastos_labels = [];
$gastos_data = [];
$gastos_cantidad = [];

while ($row = $result_grafico->fetch_assoc()) {
    $gastos_labels[] = $row['periodo'];
    $gastos_data[] = $row['total'];
    $gastos_cantidad[] = $row['cantidad'];
}

    // Consulta para la tabla de gastos
    $sql_tabla = "SELECT id, gasto_tipo, descripcion, monto, fecha FROM gastos" . $where_clause;
    if ($gasto_tipo_filtro !== 'todos') {
        $sql_tabla .= " AND gasto_tipo = ?";
    }
    $sql_tabla .= " ORDER BY fecha DESC";

    $stmt_tabla = $conn->prepare($sql_tabla);
    if (!$stmt_tabla) {
        throw new Exception("Error en la preparación de la consulta de la tabla: " . $conn->error);
    }

    // Bind de parámetros para la tabla
    if ($time_range === 'custom') {
        if ($gasto_tipo_filtro !== 'todos') {
            $stmt_tabla->bind_param('isss', $sucursal_id, $start_date, $end_date, $gasto_tipo_filtro);
        } else {
            $stmt_tabla->bind_param('iss', $sucursal_id, $start_date, $end_date);
        }
    } else {
        if ($gasto_tipo_filtro !== 'todos') {
            $stmt_tabla->bind_param('is', $sucursal_id, $gasto_tipo_filtro);
        } else {
            $stmt_tabla->bind_param('i', $sucursal_id);
        }
    }

    // Ejecutar consulta de la tabla
    if (!$stmt_tabla->execute()) {
        throw new Exception("Error al ejecutar la consulta de la tabla: " . $stmt_tabla->error);
    }
    $result_tabla = $stmt_tabla->get_result();
    $gastos = $result_tabla->fetch_all(MYSQLI_ASSOC);

    // Determinar el enlace del dashboard según el rol
    $dashboard_link = 'dashboard.php';
    switch ($rol_id) {
        case 1:
            $dashboard_link = 'dashboard_owner.php';
            break;
        case 2:
            $dashboard_link = 'dashboard_ti.php';
            break;
        case 3:
            $dashboard_link = 'dashboard_jefe.php';
            break;
        case 4:
            $dashboard_link = 'dashboard_encargado.php';
            break;
    }
} catch (Exception $e) {
    // Podrías registrar el error en un archivo log
    error_log($e->getMessage());
    echo "Error: " . $e->getMessage(); // Para pruebas, evita mostrar esto en producción
} finally {
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Gastos</title>
        <!-- Favicon -->
    <link rel="icon" href="/images/favicon.ico" type="image/x-icon">
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
                        <a href="<?php echo $dashboard_link; ?>" class="flex items-center text-white hover:bg-blue-600 p-2 rounded">
                            <span class="material-icons mr-2">dashboard</span>
                            Dashboard
                        </a>
                    </li>
                    <li class="mb-4">
                        <a href="ventas.php" class="flex items-center text-white hover:bg-blue-600 p-2 rounded">
                            <span class="material-icons mr-2">shopping_cart</span>
                            Ventas
                        </a>
                    </li>
                    <li class="mb-4">
                        <a href="#" class="flex items-center text-white bg-blue-600 p-2 rounded dropdown-toggle">
                            <span class="material-icons mr-2">monetization_on</span>
                            Gastos
                            <span class="material-icons ml-auto">arrow_drop_down</span>
                        </a>
                        <!-- Submenú -->
                        <?php if ($rol_id != 4): ?>
                        <ul class="ml-4 mt-2 hidden dropdown-menu">
                            <li class="mb-2">
                                <a href="sueldos.php" class="flex items-center text-white hover:bg-blue-500 p-2 rounded">
                                    <span class="material-icons mr-2">attach_money</span>
                                    Sueldos
                                </a>
                            </li>
                            <li class="mb-2">
                                <a href="trabajadores.php" class="flex items-center text-white hover:bg-blue-500 p-2 rounded">
                                    <span class="material-icons mr-2">people</span>
                                    Trabajadores
                                </a>
                            </li>
                            <li class="mb-2">
                                <a href="departamentos.php" class="flex items-center text-white hover:bg-blue-500 p-2 rounded">
                                    <span class="material-icons mr-2">people</span>
                                    Departamentos
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="mb-4">
                        <a href="sucursales.php" class="flex items-center text-white hover:bg-blue-600 p-2 rounded">
                            <span class="material-icons mr-2">store</span>
                            Sucursales
                        </a>
                    </li>
                    <li class="mb-4">
                        <a href="usuarios.php" class="flex items-center text-white hover:bg-blue-600 p-2 rounded">
                            <span class="material-icons mr-2">people</span>
                            Usuarios
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </aside>

        <script>
    $(document).ready(function() {
        // Manejar el clic en el toggle del dropdown
        $('.dropdown-toggle').click(function(e) {
            e.preventDefault(); // Evita la acción por defecto del enlace
            $(this).next('.dropdown-menu').toggleClass('hidden'); // Alterna la clase 'hidden' en el menú
        });

        // Cerrar el dropdown si se hace clic fuera de él
        $(document).click(function(event) {
            if (!$(event.target).closest('.dropdown-toggle').length) {
                $('.dropdown-menu').addClass('hidden'); // Oculta el menú
            }
        });
    });
</script>

        <!-- Main Content -->
        <main class="content flex-grow p-8">
            <h1 class="text-3xl font-bold text-center mb-5">Gastos - Sucursal: <?php echo htmlspecialchars($sucursal_nombre); ?></h1>

            <!-- Formulario de filtros -->
            <form method="GET" id="filterForm" class="mb-6 space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label for="time_range" class="block text-lg font-semibold mb-2">Rango de Tiempo:</label>
                        <select name="time_range" id="time_range" class="border p-2 rounded-md w-full">
                            <option value="day" <?php echo $time_range == 'day' ? 'selected' : ''; ?>>Diario</option>
                            <option value="month" <?php echo $time_range == 'month' ? 'selected' : ''; ?>>Mensual</option>
                            <option value="year" <?php echo $time_range == 'year' ? 'selected' : ''; ?>>Anual</option>
                            <option value="custom" <?php echo $time_range == 'custom' ? 'selected' : ''; ?>>Personalizado</option>
                        </select>
                    </div>

                    <div>
                        <label for="gasto_tipo_filtro" class="block text-lg font-semibold mb-2">Tipo de Gasto:</label>
                        <select name="gasto_tipo_filtro" id="gasto_tipo_filtro" class="border p-2 rounded-md w-full">
                            <option value="todos" <?php echo $gasto_tipo_filtro == 'todos' ? 'selected' : ''; ?>>Todos</option>
                            <option value="fijo" <?php echo $gasto_tipo_filtro == 'fijo' ? 'selected' : ''; ?>>Fijo</option>
                            <option value="variable" <?php echo $gasto_tipo_filtro == 'variable' ? 'selected' : ''; ?>>Variable</option>
                        </select>
                    </div>
                </div>

                <div id="custom_dates" class="grid grid-cols-1 sm:grid-cols-2 gap-4 <?php echo $time_range === 'custom' ? '' : 'hidden'; ?>">
                    <div>
                        <label for="start_date" class="block text-lg font-semibold mb-2">Fecha Inicio:</label>
                        <input type="date" name="start_date" id="start_date" value="<?php echo $start_date; ?>" class="border p-2 rounded-md w-full">
                    </div>
                    <div>
                        <label for="end_date" class="block text-lg font-semibold mb-2">Fecha Fin:</label>
                        <input type="date" name="end_date" id="end_date" value="<?php echo $end_date; ?>" class="border p-2 rounded-md w-full">
                    </div>
                </div>

                <div class="text-center">
                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700">
                        Filtrar
                    </button>
                </div>
            </form>

            <!-- Gráfico y Tabla -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Gráfico -->
                <div class="card p-4">
                    <h2 class="text-xl font-bold mb-4">Gráfico de Gastos</h2>
                    <div class="chart-container">
                        <canvas id="gastosChart"></canvas>
                    </div>
                </div>

<!-- Formulario de Registro -->
<div class="card p-4">
    <h2 class="text-xl font-bold mb-4">Registrar Nuevo Gasto</h2>
    <form method="POST" id="gastoForm" class="space-y-4">
        <div>
            <label for="gasto_tipo" class="block text-sm font-medium text-gray-700">Tipo de Gasto</label>
            <select name="gasto_tipo" id="gasto_tipo" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" onchange="toggleFields('registro')">
                <option value="fijo">Gasto Fijo</option>
                <option value="variable">Gasto Variable</option>
            </select>
        </div>
        <div id="gastosFijos" class="hidden">
            <label for="gastos-fijos" class="block text-sm font-medium text-gray-700">Gastos Fijos</label>
            <select id="gastos-fijos" name="gastos-fijos" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2">
                <option value="luz">Luz</option>
                <option value="agua">Agua</option>
                <option value="gas">Gas</option>
                <option value="internet">Internet</option>
                <option value="otros">Otros</option>
            </select>
        </div>
        <div id="descripcionContainer" class="hidden">
            <label for="tipo" class="block text-sm font-medium text-gray-700">Descripción</label>
            <input type="text" name="tipo" id="tipo" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2">
        </div>
        <div>
            <label for="monto" class="block text-sm font-medium text-gray-700">Monto</label>
            <input type="number" name="monto" id="monto" required step="0.01" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2">
        </div>
        <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
            Registrar Gasto
        </button>
    </form>
</div>

                <!-- Tabla de Gastos -->
                <div class="card p-4">
                    <h2 class="text-xl font-bold mb-4">Lista de Gastos</h2>
                    <div class="overflow-x-auto">
                        <table id="gastosTable" class="display responsive nowrap w-full">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tipo</th>
                                    <th>Descripción</th>
                                    <th>Monto</th>
                                    <th>Fecha</th>
                                    <?php if ($rol_id == 2 || $rol_id == 3) : ?>
                                        <th>Acciones</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($gastos as $gasto): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($gasto['id']); ?></td>
                                    <td><?php echo htmlspecialchars($gasto['gasto_tipo']); ?></td>
                                    <td><?php echo htmlspecialchars($gasto['descripcion']); ?></td>
                                    <td>$<?php echo number_format($gasto['monto'], 0, ',', '.'); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($gasto['fecha'])); ?></td>
                                    <?php if ($rol_id == 2 || $rol_id == 3) : ?>
                                        <td class="flex gap-2">
                                            <button onclick="mostrarFormularioEdicion(<?php echo $gasto['id']; ?>, '<?php echo htmlspecialchars($gasto['descripcion']); ?>', <?php echo $gasto['monto']; ?>, '<?php echo $gasto['gasto_tipo']; ?>')" 
                                                    class="bg-yellow-500 text-white p-2 rounded hover:bg-yellow-600">
                                                <i class="material-icons">edit</i>
                                            </button>
                                            <button onclick="confirmarEliminacion(<?php echo $gasto['id']; ?>)" 
                                                    class="bg-red-500 text-white p-2 rounded hover:bg-red-600">
                                                <i class="material-icons">delete</i>
                                            </button>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        
<!-- Modal de Edición -->
<div id="editModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 hidden">
    <div class="bg-white rounded-lg p-6 w-96">
        <h2 class="text-xl font-bold mb-4">Editar Gasto</h2>
        <form id="editForm" method="POST">
            <input type="hidden" name="accion" value="editar">
            <input type="hidden" name="gasto_id" id="modal_gasto_id">

            <div class="mb-4">
                <label for="modal_gasto_tipo" class="block text-sm font-medium text-gray-700">Tipo de Gasto</label>
                <select name="gasto_tipo" id="modal_gasto_tipo" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" onchange="toggleFields('modal')">
                    <option value="fijo">Gasto Fijo</option>
                    <option value="variable">Gasto Variable</option>
                </select>
            </div>

            <div id="gastosFijos" class="hidden">
                <label for="gastos-fijos" class="block text-sm font-medium text-gray-700">Gastos Fijos</label>
                <select id="gastos-fijos" name="gastos-fijos" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2">
                    <option value="luz">Luz</option>
                    <option value="agua">Agua</option>
                    <option value="gas">Gas</option>
                    <option value="internet">Internet</option>
                    <option value="otros">Otros</option>
                </select>
            </div>

            <div id="descripcionContainer" class="mb-4">
                <label for="modal_descripcion" class="block text-sm font-medium text-gray-700">Descripción</label>
                <input type="text" name="tipo" id="modal_descripcion" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2">
            </div>

            <div class="mb-4">
                <label for="modal_monto" class="block text-sm font-medium text-gray-700">Monto</label>
                <input type="number" name="monto" id="modal_monto" required step="0.01" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2">
            </div>

            <div class="flex justify-end">
                <button type="button" onclick="closeModal()" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md mr-2">Cancelar</button>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md">Guardar</button>
            </div>
        </form>
    </div>
</div>


<script>
    $(document).ready(function() {
        // Inicializa la tabla de gastos
        $('#gastosTable').DataTable({
            responsive: true,
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.11.5/i18n/Spanish.json' // Carga el idioma español
            }
        });

        // Gráfico de gastos
        const ctx = document.getElementById('gastosChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($gastos_labels); ?>,
                datasets: [{
                    label: 'Total de Gastos',
                    data: <?php echo json_encode($gastos_data); ?>,
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    borderColor: 'rgb(75, 192, 192)',
                    borderWidth: 1,
                    yAxisID: 'y'
                }, {
                    label: 'Cantidad de Gastos',
                    data: <?php echo json_encode($gastos_cantidad); ?>,
                    type: 'line',
                    borderColor: 'rgb(255, 99, 132)',
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            unit: '<?php echo ($time_range === 'day') ? 'day' : (($time_range === 'month') ? 'month' : 'year'); ?>',
                            tooltipFormat: '<?php echo ($time_range === 'day') ? 'MMM d, yyyy' : (($time_range === 'month') ? 'MMM yyyy' : 'yyyy'); ?>',
                            displayFormats: {
                                day: 'MMM d',
                                month: 'MMM yyyy',
                                year: 'yyyy'
                            }
                        },
                        title: {
                            display: true,
                            text: '<?php echo ucfirst($time_range); ?>'
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + new Intl.NumberFormat('es-CL').format(value);
                            }
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        beginAtZero: true,
                        grid: {
                            drawOnChartArea: false,
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                if (context.dataset.label === 'Total de Gastos') {
                                    return 'Total: $' + new Intl.NumberFormat('es-CL').format(context.raw);
                                } else {
                                    return 'Cantidad: ' + context.raw;
                                }
                            }
                        }
                    }
                }
            }
        });

        // Función para mostrar el formulario de edición
        window.mostrarFormularioEdicion = function(id, descripcion, monto, gastoTipo) {
            document.getElementById('modal_gasto_id').value = id;
            document.getElementById('modal_descripcion').value = descripcion;
            document.getElementById('modal_monto').value = monto;
            document.getElementById('modal_gasto_tipo').value = gastoTipo;

            // Mostrar el modal
            document.getElementById('editModal').classList.remove('hidden');
            toggleFields('modal');
        }

        // Función para cerrar el modal
        window.closeModal = function() {
            document.getElementById('editModal').classList.add('hidden');
        }
        
        // Función para confirmar y procesar la eliminación
        window.confirmarEliminacion = function(id) {
            if (confirm("¿Estás seguro de que deseas eliminar este gasto?")) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="gasto_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

         // Función para mostrar u ocultar campos según el tipo de gasto
         window.toggleFields = function(context = '') {
            let gastoTipo, gastosFijos, descripcionContainer, descripcionInput, gastosFijosSelect;

            if (context === 'modal') {
                gastoTipo = document.getElementById('modal_gasto_tipo').value;
                gastosFijos = document.getElementById('gastosFijos');
                descripcionContainer = document.getElementById('descripcionContainer');
                descripcionInput = document.getElementById('modal_descripcion');
                gastosFijosSelect = document.getElementById('gastos-fijos');
            } else if (context === 'registro') {
                gastoTipo = document.getElementById('gasto_tipo').value;
                gastosFijos = document.getElementById('gastosFijos');
                descripcionContainer = document.getElementById('descripcionContainer');
                descripcionInput = document.getElementById('tipo');
                gastosFijosSelect = document.getElementById('gastos-fijos');
            }

            if (gastoTipo === 'fijo') {
                gastosFijos.classList.remove('hidden');
                descripcionContainer.classList.add('hidden');
                descripcionInput.removeAttribute('required');
                gastosFijosSelect.setAttribute('required', 'required');
            } else {
                gastosFijos.classList.add('hidden');
                descripcionContainer.classList.remove('hidden');
                descripcionInput.setAttribute('required', 'required');
                gastosFijosSelect.removeAttribute('required');
            }
        }
    });
</script>
</body>
</html>
