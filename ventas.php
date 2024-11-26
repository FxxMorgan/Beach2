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

// Función para obtener permisos de un usuario
function obtenerPermisos($conn, $usuario_id) {
    $sql = "SELECT permiso, estado FROM permisos WHERE usuario_id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $permisos = [];
        while ($row = $result->fetch_assoc()) {
            $permisos[$row['permiso']] = $row['estado'];
        }
        $stmt->close();
        return $permisos;
    }
    return [];
}

// Obtener permisos del usuario actual
$permisos = obtenerPermisos($conn, $usuario_id);

// Verificar si el usuario tiene permiso para acceder a ventas
if (!isset($permisos['ventas']) || $permisos['ventas'] != 1) {
    echo "<script>alert('No tienes permiso para acceder a esta página.');</script>";
    header("Location: dashboard.php"); // Redirigir a una página segura
    exit();
}

// Determinar el enlace del dashboard según el rol
$dashboard_link = 'dashboard.php'; // Valor por defecto
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

// Lógica de eliminar venta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'eliminar') {
    $venta_id = $_POST['venta_id'];

    $sql = "DELETE FROM ventas WHERE id = ? AND sucursal_id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ii", $venta_id, $sucursal_id);
        if ($stmt->execute()) {
            echo "<script>alert('Venta eliminada con éxito.'); window.location.href='ventas.php';</script>";
        } else {
            echo "<script>alert('Error al eliminar la venta.');</script>";
        }
        $stmt->close();
    }
}

// Lógica de editar venta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'editar') {
    $venta_id = $_POST['venta_id'];
    $nueva_descripcion = $_POST['nueva_descripcion'];
    $nuevo_monto = $_POST['nuevo_monto'];

    $sql = "UPDATE ventas SET descripcion = ?, monto = ? WHERE id = ? AND sucursal_id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("siii", $nueva_descripcion, $nuevo_monto, $venta_id, $sucursal_id);
        if ($stmt->execute()) {
            echo "<script>alert('Venta actualizada con éxito.'); window.location.href='ventas.php';</script>";
        } else {
            echo "<script>alert('Error al actualizar la venta.');</script>";
        }
        $stmt->close();
    }
}

// Obtener datos de ventas para mostrar
$ventas = [];
$sql_ventas = "SELECT id, fecha, descripcion, monto FROM ventas WHERE sucursal_id = ?";
$stmt_ventas = $conn->prepare($sql_ventas);
if ($stmt_ventas) {
    $stmt_ventas->bind_param("i", $sucursal_id);
    $stmt_ventas->execute();
    $result_ventas = $stmt_ventas->get_result();
    while ($row = $result_ventas->fetch_assoc()) {
        $ventas[] = $row;
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
    <title>Ventas</title>
    <!-- Favicon -->
    <link rel="icon" href="/images/favicon.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns"></script>

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
                        <a id="dashboard-link" href="<?php echo $dashboard_link; ?>" class="flex items-center text-white hover:bg-blue-600 p-2 rounded">
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
                    <?php if ($rol_id != 4): ?>
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
                        <a id="salario-link" href="sueldos.php" class="flex items-center text-white hover:bg-blue-600 p-2 rounded">
                            <span class="material-icons mr-2">attach_money</span>
                            Sueldos
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-8">
            <h1 class="text-3xl font-bold mb-8">Ventas</h1>

            <!-- Formulario para Ingresar Ventas Diarias -->
            <div class="bg-white p-6 rounded shadow mb-8">
                <h2 class="text-xl font-semibold mb-4">Registrar Venta Diaria</h2>
                <form action="ventas.php" method="post">
                    <input type="hidden" name="tipo_formulario" value="venta">
                    <label for="descripcion" class="block mb-2">Descripción:</label>
                    <input type="text" id="descripcion" name="descripcion" class="w-full mb-4 p-2 border" required>

                    <label for="monto" class="block mb-2">Monto (CLP):</label>
                    <input type="number" id="monto" name="monto" class="w-full mb-4 p-2 border" required>

                    <button type="submit" class="bg-blue-500 text-white p-2 rounded">Registrar Venta</button>
                </form>
            </div>

            <!-- Gráfico de Ventas -->
            <div class="bg-white p-6 rounded shadow mb-8">
                <h2 class="text-xl font-semibold mb-4">Gráfico de Ventas</h2>
                <canvas id="ventasChart"></canvas>
            </div>


            <!-- Mostrar Ventas Existentes -->
            <div class="bg-white p-6 rounded shadow mb-8">
                <h2 class="text-xl font-semibold mb-4">Historial de Ventas</h2>
                <table id="ventasTable" class="display w-full">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Descripción</th>
                            <th>Monto</th>
                            <?php if ($rol_id == 2 || $rol_id == 3) : ?>
                                <th>Acciones</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ventas as $venta) : ?>
                            <tr>
                                <td><?php echo $venta['fecha']; ?></td>
                                <td><?php echo $venta['descripcion']; ?></td>
                                <td><?php echo number_format($venta['monto'], 0, ',', '.'); ?></td>
                                <?php if ($rol_id == 2 || $rol_id == 3) : ?>
                                    <td class="flex gap-2">
                                        <button onclick="mostrarFormularioEdicion(<?php echo $venta['id']; ?>, '<?php echo htmlspecialchars($venta['descripcion']); ?>', <?php echo $venta['monto']; ?>)" 
                                                class="bg-yellow-500 text-white p-2 rounded hover:bg-yellow-600">
                                            <i class="material-icons">edit</i>
                                        </button>
                                        <button onclick="confirmarEliminacion(<?php echo $venta['id']; ?>)" 
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
        </main>
    </div>

    <script>
        $(document).ready(function() {
            $('#ventasTable').DataTable();

            // Gráfico de Ventas
            const ctx = document.getElementById('ventasChart').getContext('2d');
            const ventasData = {
                labels: [<?php foreach ($ventas as $venta) { echo "'" . date('Y-m-d', strtotime($venta['fecha'])) . "',"; } ?>],
                datasets: [{
                    label: 'Ventas Diarias',
                    data: [<?php foreach ($ventas as $venta) { echo $venta['monto'] . ","; } ?>],
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1,
                    tension: 0.3 // Suaviza las líneas
                }]
            };

            const ventasChart = new Chart(ctx, {
                type: 'line',
                data: ventasData,
                options: {
                    responsive: true,
                    scales: {
                        x: {
                            type: 'time',
                            time: {
                                unit: 'day',
                                tooltipFormat: 'dd-MM-yyyy'
                            }
                        },
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Monto (CLP)'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    }
                }
            });
        });

        // Función para mostrar el formulario de edición mediante prompts
        function mostrarFormularioEdicion(id, descripcion, monto) {
            const nuevaDescripcion = prompt("Nueva descripción:", descripcion);
            if (nuevaDescripcion === null) return;

            const nuevoMonto = prompt("Nuevo monto:", monto);
            if (nuevoMonto === null) return;

            if (nuevaDescripcion.trim() === "" || isNaN(nuevoMonto) || nuevoMonto <= 0) {
                alert("Por favor, ingrese datos válidos");
                return;
            }

            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="accion" value="editar">
                <input type="hidden" name="venta_id" value="${id}">
                <input type="hidden" name="nueva_descripcion" value="${nuevaDescripcion}">
                <input type="hidden" name="nuevo_monto" value="${nuevoMonto}">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        // Función para confirmar y procesar la eliminación
        function confirmarEliminacion(id) {
            if (confirm("¿Estás seguro de que deseas eliminar esta venta?")) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="venta_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>

</html>
