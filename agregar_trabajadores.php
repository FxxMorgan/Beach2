<?php
session_start();

// Verificar si el usuario tiene un rol permitido (2 o 3)
if (!isset($_SESSION['rol_id']) || ($_SESSION['rol_id'] != 2 && $_SESSION['rol_id'] != 3)) {
    echo "No tienes permiso para acceder a esta página.";
    exit();
}

// Obtener el rol del usuario
$rol_id = $_SESSION['rol_id']; // Asegúrate de definir esta variable

// Conexión a la base de datos
$conn = new mysqli('localhost', 'root', '', 'beach2');

// Verificar la conexión
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

// Obtener el usuario_id de la sesión
$usuario_id = $_SESSION['usuario_id'];

// Obtener la sucursal del usuario actual
$sql = "SELECT sucursal_id FROM usuarios WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();
$usuario_sucursal = $result->fetch_assoc()['sucursal_id'];
$stmt->close();

// Función para obtener permisos del usuario
function obtenerPermisos($conn, $usuario_id) {
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

// Verificar si el usuario tiene permiso para agregar trabajadores
$permisos_usuario = obtenerPermisos($conn, $usuario_id);
if (!in_array('agregar_trabajador', $permisos_usuario)) {
    echo "No tienes permiso para gestionar sueldos.";
    // Redirigir al dashboard después de 1 segundo
    echo "<script>
            setTimeout(function() {
                window.location.href = 'dashboard.php';
            }, 1000);
          </script>";
    exit();
}

// Obtener solo la sucursal del usuario para el formulario
$sql = "SELECT id, nombre, direccion FROM sucursales WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $usuario_sucursal);
$stmt->execute();
$result = $stmt->get_result();
$sucursales = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Manejar la solicitud de agregar trabajador
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'];
    $puesto = $_POST['puesto'];
    $sucursal_id = $_POST['sucursal_id'];
    $usuario_id = $_SESSION['usuario_id']; // Obtener el ID del usuario actual

    // Modificar la consulta para incluir usuario_id
    $sql = "INSERT INTO trabajadores (nombre, puesto, sucursal_id, usuario_id) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssii", $nombre, $puesto, $sucursal_id, $usuario_id); // Asegúrate de ajustar el tipo de datos

    if ($stmt->execute()) {
        echo "<div class='alert alert-success'>Trabajador agregado exitosamente.</div>";
    } else {
        echo "<div class='alert alert-danger'>Error al agregar trabajador.</div>";
    }
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


// Cerrar conexión
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agregar Trabajador</title>
        <!-- Favicon -->
    <link rel="icon" href="/images/favicon.ico" type="image/x-icon">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">

    <!-- Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">

    <!-- Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

    <style>
        /* Estilos personalizados */
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
            padding: 20px;
        }
        .card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            padding: 20px;
        }
    </style>
</head>

<body>
    <div class="flex">
        <!-- Sidebar -->
        <aside class="sidebar fixed h-screen w-64 p-4">
            <nav>
                <ul>
                    <li class="mb-4">
                        <a href="<?php echo $dashboard_link; ?>" class="flex items-center text-white hover:bg-blue-600 p-2 rounded">
                            <span class="material-icons mr-2">dashboard</span>
                            Dashboard
                        </a>
                    </li>
                    <li class="mb-4">
                        <a href="sueldos.php" class="flex items-center text-white hover:bg-blue-600 p-2 rounded">
                            <span class="material-icons mr-2">attach_money</span>
                            Registro de Salarios
                        </a>
                    </li>
                    <li class="mb-4">
                        <a href="agregar_trabajadores.php" class="flex items-center text-white hover:bg-blue-600 bg-blue-600 p-2 rounded">
                            <span class="material-icons mr-2">people</span>
                            Agregar Trabajador
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="content flex-grow">
            <h1 class="text-3xl font-bold mb-8">Agregar Trabajador</h1>

            <!-- Formulario para Agregar Trabajador -->
            <div class="card">
                <h2 class="text-xl font-semibold mb-4">Registrar Nuevo Trabajador</h2>
                <form method="POST" action="agregar_trabajadores.php">
                    <div class="mb-4">
                        <label for="nombre" class="block text-sm font-medium text-gray-700">Nombre:</label>
                        <input type="text" name="nombre" id="nombre" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <div class="mb-4">
                        <label for="puesto" class="block text-sm font-medium text-gray-700">Puesto:</label>
                        <input type="text" name="puesto" id="puesto" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <div class="mb-4">
    <label for="sucursal_id" class="block text-sm font-medium text-gray-700">Sucursal:</label>
    <select name="sucursal_id" id="sucursal_id" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 p-2">
        <?php foreach ($sucursales as $sucursal): ?>
            <option value="<?php echo $sucursal['id']; ?>">
                <?php echo htmlspecialchars($sucursal['nombre'] . " - " . $sucursal['direccion']); ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>


                    <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Agregar Trabajador
                    </button>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
