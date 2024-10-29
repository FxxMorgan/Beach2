<?php
session_start();

// Mostrar las variables de sesión para depuración
echo "<pre>";
var_dump($_SESSION);
echo "</pre>";

// Verificar si el usuario ha iniciado sesión y tiene el rol adecuado
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] != 2) {
    header("Location: login.php");
    exit();
}

// Conexión a la base de datos
$conn = new mysqli('localhost', 'root', '', 'beach2');

// Verificar la conexión
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

// Función para obtener todos los usuarios
function obtenerUsuarios($conn) {
    $sql = "SELECT id, email, rol_id FROM usuarios";
    $result = $conn->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Función para obtener permisos de un usuario
function obtenerPermisos($conn, $usuario_id) {
    $sql = "SELECT permiso, estado FROM permisos WHERE usuario_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $permisos = [];
    while ($row = $result->fetch_assoc()) {
        $permisos[$row['permiso']] = $row['estado'];
    }
    return $permisos;
}

// Función para actualizar permisos
function actualizarPermisos($conn, $usuario_id, $permisos) {
    foreach ($permisos as $permiso => $estado) {
        $sql = "REPLACE INTO permisos (usuario_id, permiso, estado) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isi", $usuario_id, $permiso, $estado);
        $stmt->execute();
    }
}

// Procesar el formulario de actualización de permisos
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['usuario_id'], $_POST['permisos'])) {
    $usuario_id = $_POST['usuario_id'];
    $permisos_seleccionados = $_POST['permisos'];

    // Establecer todos los permisos a 0 inicialmente
    $permisos = [
        'ventas' => 0,
        'reportes' => 0,
        'inventario' => 0,
        'usuarios' => 0
    ];

    // Actualizar los permisos seleccionados a 1
    foreach ($permisos_seleccionados as $permiso) {
        if (array_key_exists($permiso, $permisos)) {
            $permisos[$permiso] = 1;
        }
    }

    actualizarPermisos($conn, $usuario_id, $permisos);
    echo "<script>alert('Permisos actualizados correctamente.');</script>";
}

$usuarios = obtenerUsuarios($conn);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard TI</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/notyf/3.10.0/notyf.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns"></script>
    <!-- Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f9;
        }
        header {
            background-color: #0073e6;
            color: #ffffff;
            padding: 10px 0;
            text-align: center;
        }
        main {
            padding: 20px;
        }
        footer {
            background-color: #333;
            color: #ffffff;
            text-align: center;
            padding: 10px 0;
            position: fixed;
            width: 100%;
            bottom: 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
        }
        th {
            background-color: #f2f2f2;
        }
    </style>
</head>
<body>

<header>
    <h1>Bienvenido al Dashboard TI</h1>
</header>

<li class="mb-4">
    <a id="dashboard-link" href="index.php" class="flex items-center text-white hover:bg-blue-600 p-2 rounded">
        <span class="material-icons mr-2">dashboard</span>
        Dashboard
    </a>
</li>

<main>
    <h2>Gestión de Permisos</h2>
    <table>
        <tr>
            <th>ID Usuario</th>
            <th>Email</th>
            <th>Permisos</th>
            <th>Acción</th>
        </tr>
        <?php foreach ($usuarios as $usuario): ?>
        <?php $permisos = obtenerPermisos($conn, $usuario['id']); ?>
        <tr>
            <form method="post">
                <td><?php echo $usuario['id']; ?></td>
                <td><?php echo $usuario['email']; ?></td>
                <td>
                    <input type="checkbox" name="permisos[]" value="ventas" <?php if (isset($permisos['ventas']) && $permisos['ventas'] == 1) echo 'checked'; ?>> Ventas<br>
                    <input type="checkbox" name="permisos[]" value="reportes" <?php if (isset($permisos['reportes']) && $permisos['reportes'] == 1) echo 'checked'; ?>> Reportes<br>
                    <input type="checkbox" name="permisos[]" value="inventario" <?php if (isset($permisos['inventario']) && $permisos['inventario'] == 1) echo 'checked'; ?>> Inventario<br>
                    <input type="checkbox" name="permisos[]" value="usuarios" <?php if (isset($permisos['usuarios']) && $permisos['usuarios'] == 1) echo 'checked'; ?>> Usuarios<br>
                </td>
                <td>
                    <input type="hidden" name="usuario_id" value="<?php echo $usuario['id']; ?>">
                    <button type="submit">Actualizar</button>
                </td>
            </form>
        </tr>
        <?php endforeach; ?>
    </table>
</main>

<footer>
    <p>&copy; 2024 Tu Compañía. Todos los derechos reservados.</p>
</footer>

<script src="https://cdnjs.cloudflare.com/ajax/libs/notyf/3.10.0/notyf.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const notyf = new Notyf();
        notyf.success('Bienvenido al panel de TI');
    });
</script>

</body>
</html>

<?php
// Cerrar la conexión al final del script
$conn->close();
?>
