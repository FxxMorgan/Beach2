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
    if ($rol_id == 3) { // Jefe de local
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
    $stmt->close();
    return $permisos;
}

$permisos_usuario = obtenerPermisos($conn, $usuario_id, $rol_id);

// Verificar si el usuario tiene permiso para acceder al Dashboard
if (!in_array('dashboard', $permisos_usuario)) {
    echo "No tienes permiso para acceder a esta página.";
    exit();
}

// Crear cheque
$mensaje_exito = '';
$mensaje_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['crear_cheque'])) {
        $descripcion = $_POST['descripcion'];
        $monto = $_POST['monto'];
        $fecha_pago = $_POST['fecha_pago'];

        // Validar entradas
        if (!empty($descripcion) && is_numeric($monto) && !empty($fecha_pago)) {
            $sql_crear_cheque = "INSERT INTO cheques (descripcion, monto, fecha_pago, sucursal_id) VALUES (?, ?, ?, ?)";
            $stmt_crear_cheque = $conn->prepare($sql_crear_cheque);
            $stmt_crear_cheque->bind_param("sdsi", $descripcion, $monto, $fecha_pago, $sucursal_id);

            if ($stmt_crear_cheque->execute()) {
                $mensaje_exito = "Cheque creado exitosamente.";
            } else {
                $mensaje_error = "Error al crear cheque: " . $stmt_crear_cheque->error;
            }
            $stmt_crear_cheque->close();
        } else {
            $mensaje_error = "Por favor, completa todos los campos correctamente.";
        }
    }

    // Marcar cheque como listo
    if (isset($_POST['marcar_listo'])) {
        $cheque_id = $_POST['cheque_id'];
        $sql_marcar_listo = "UPDATE cheques SET estado = 'listo' WHERE id = ? AND sucursal_id = ?";
        $stmt_marcar_listo = $conn->prepare($sql_marcar_listo);
        $stmt_marcar_listo->bind_param("ii", $cheque_id, $sucursal_id);

        if ($stmt_marcar_listo->execute()) {
            $mensaje_exito = "Cheque marcado como listo.";
        } else {
            $mensaje_error = "Error al marcar el cheque como listo: " . $stmt_marcar_listo->error;
        }
        $stmt_marcar_listo->close();
    }

    // Eliminar cheque
    if (isset($_POST['eliminar_cheque'])) {
        $cheque_id = $_POST['cheque_id_eliminar'];
        $sql_eliminar_cheque = "DELETE FROM cheques WHERE id = ? AND sucursal_id = ?";
        $stmt_eliminar_cheque = $conn->prepare($sql_eliminar_cheque);
        $stmt_eliminar_cheque->bind_param("ii", $cheque_id, $sucursal_id);

        if ($stmt_eliminar_cheque->execute()) {
            $mensaje_exito = "Cheque eliminado exitosamente.";
        } else {
            $mensaje_error = "Error al eliminar cheque: " . $stmt_eliminar_cheque->error;
        }
        $stmt_eliminar_cheque->close();
    }
}

// Fecha actual y límite
$fecha_actual = date('Y-m-d');
$fecha_limite = date('Y-m-d', strtotime('+7 days'));

// Obtener cheques pendientes de pago
$sql_cheques = "SELECT id, descripcion, monto, fecha_pago, estado FROM cheques WHERE sucursal_id = ? AND fecha_pago >= ?";
$stmt_cheques = $conn->prepare($sql_cheques);
$stmt_cheques->bind_param("is", $sucursal_id, $fecha_actual);
$stmt_cheques->execute();
$result_cheques = $stmt_cheques->get_result();
$cheques = $result_cheques->fetch_all(MYSQLI_ASSOC);
$stmt_cheques->close();

// Obtener cheques a punto de vencer (en los próximos 7 días)
$sql_cheques_vencer = "SELECT COUNT(*) as count FROM cheques WHERE sucursal_id = ? AND fecha_pago BETWEEN ? AND ?";
$stmt_cheques_vencer = $conn->prepare($sql_cheques_vencer);
$stmt_cheques_vencer->bind_param("iss", $sucursal_id, $fecha_actual, $fecha_limite);
$stmt_cheques_vencer->execute();
$result_vencer = $stmt_cheques_vencer->get_result();
$row_vencer = $result_vencer->fetch_assoc();
$cheques_proximos_a_vencer = $row_vencer['count'];
$stmt_cheques_vencer->close();

// Obtener detalles de cheques próximos a vencer
$sql_cheques_proximos = "SELECT descripcion, fecha_pago FROM cheques WHERE sucursal_id = ? AND fecha_pago BETWEEN ? AND ?";
$stmt_cheques_proximos = $conn->prepare($sql_cheques_proximos);
$stmt_cheques_proximos->bind_param("iss", $sucursal_id, $fecha_actual, $fecha_limite);
$stmt_cheques_proximos->execute();
$result_cheques_proximos = $stmt_cheques_proximos->get_result();
$cheques_proximos = $result_cheques_proximos->fetch_all(MYSQLI_ASSOC);
$stmt_cheques_proximos->close();

// Cerrar la conexión
$conn->close();
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

    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f4f6f9;
        }

        .sidebar {
            background-color: #2c3e50;
            color: #ecf0f1;
        }

        .sidebar nav ul li a:hover {
            background-color: #34495e;
            transition: background-color 0.3s;
        }

        .sidebar nav ul li a.active {
            background-color: #1abc9c;
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

        .notification-icon {
            position: relative;
            cursor: pointer;
            display: flex;
            align-items: center;
        }

        .notification-icon .badge {
            position: absolute;
            top: -5px;
            right: -10px;
            background-color: #e74c3c;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 12px;
        }

        .inline-form {
            display: inline;
        }

        /* Button Styles */
        .btn {
            background-color: #3490dc;
            color: white;
            border-radius: 4px;
            padding: 8px 16px;
            transition: background-color 0.3s;
        }

        .btn:hover {
            background-color: #2779bd;
        }

        /* Input Focus */
        input[type="text"],
        input[type="number"],
        input[type="date"] {
            border: 1px solid #ced4da;
            border-radius: 4px;
            width: 100%;
            padding: 8px;
            outline: none;
            transition: border-color 0.3s;
        }

        input[type="text"]:focus,
        input[type="number"]:focus,
        input[type="date"]:focus {
            border-color: #3490dc;
        }

        /* Notification Dropdown */
        #notificationDropdown {
            position: absolute;
            right: 0;
            margin-top: 8px;
            width: 320px;
            background-color: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
            z-index: 50;
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
            <h1 class="text-3xl font-bold mb-6">Dashboard</h1>

            <!-- Notifications -->
            <div class="relative mb-6">
                <button onclick="toggleNotifications()" class="notification-icon text-red-500 focus:outline-none">
                    <span class="material-icons">notifications</span>
                    <?php if ($cheques_proximos_a_vencer > 0): ?>
                    <span class="badge">
                        <?php echo $cheques_proximos_a_vencer; ?>
                    </span>
                    <?php endif; ?>
                </button>

                <!-- Notification Dropdown -->
                <div id="notificationDropdown" class="hidden">
                    <div class="p-4">
                        <h3 class="font-semibold mb-2">Cheques Próximos a Vencer</h3>
                        <ul>
                            <?php if (!empty($cheques_proximos)): ?>
                                <?php foreach ($cheques_proximos as $cheque): ?>
                                <li class="mb-1">
                                    <strong><?php echo htmlspecialchars($cheque['descripcion']); ?></strong> - Vence el <?php echo htmlspecialchars($cheque['fecha_pago']); ?>
                                </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li class="text-gray-600">No hay cheques próximos a vencer.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Cheques -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Crear Cheque -->
                <div class="card p-6">
                    <h2 class="text-xl font-semibold mb-4">Crear Cheque</h2>
                    <?php if ($mensaje_exito): ?>
                    <div class="bg-green-200 text-green-800 p-2 rounded mb-4">
                        <?php echo htmlspecialchars($mensaje_exito); ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($mensaje_error): ?>
                    <div class="bg-red-200 text-red-800 p-2 rounded mb-4">
                        <?php echo htmlspecialchars($mensaje_error); ?>
                    </div>
                    <?php endif; ?>
                    <form method="POST">
                        <div class="mb-4">
                            <label for="descripcion" class="block mb-1">Descripción</label>
                            <input type="text" name="descripcion" id="descripcion" required>
                        </div>
                        <div class="mb-4">
                            <label for="monto" class="block mb-1">Monto</label>
                            <input type="number" name="monto" id="monto" step="0.01" required>
                        </div>
                        <div class="mb-4">
                            <label for="fecha_pago" class="block mb-1">Fecha de Pago</label>
                            <input type="date" name="fecha_pago" id="fecha_pago" required>
                        </div>
                        <button type="submit" name="crear_cheque" class="btn">Crear Cheque</button>
                    </form>
                </div>

                <!-- Cheques Pendientes -->
                <div class="card p-6">
                    <h2 class="text-xl font-semibold mb-4">Cheques Pendientes</h2>
                    <table class="min-w-full">
                        <thead>
                            <tr>
                                <th class="border px-4 py-2">Descripción</th>
                                <th class="border px-4 py-2">Monto</th>
                                <th class="border px-4 py-2">Fecha de Pago</th>
                                <th class="border px-4 py-2">Estado</th>
                                <th class="border px-4 py-2">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cheques as $cheque): ?>
                            <tr>
                                <td class="border px-4 py-2"><?php echo htmlspecialchars($cheque['descripcion']); ?></td>
                                <td class="border px-4 py-2"><?php echo number_format($cheque['monto'], 2); ?></td>
                                <td class="border px-4 py-2"><?php echo htmlspecialchars($cheque['fecha_pago']); ?></td>
                                <td class="border px-4 py-2"><?php echo htmlspecialchars($cheque['estado']); ?></td>
                                <td class="border px-4 py-2">
                                    <form method="POST" class="inline-form">
                                        <input type="hidden" name="cheque_id" value="<?php echo $cheque['id']; ?>">
                                        <button type="submit" name="marcar_listo" class="bg-green-600 text-white rounded py-1 px-2">Listo</button>
                                    </form>
                                    <form method="POST" class="inline-form">
                                        <input type="hidden" name="cheque_id_eliminar" value="<?php echo $cheque['id']; ?>">
                                        <button type="submit" name="eliminar_cheque" class="bg-red-600 text-white rounded py-1 px-2">Eliminar</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- JavaScript for Notification Dropdown -->
    <script>
        function toggleNotifications() {
            const dropdown = document.getElementById('notificationDropdown');
            dropdown.classList.toggle('hidden');
        }

        // Close the dropdown if clicked outside
        window.addEventListener('click', function(e) {
            const dropdown = document.getElementById('notificationDropdown');
            const button = document.querySelector('.notification-icon');
            if (!button.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.classList.add('hidden');
            }
        });
    </script>
</body>

</html>