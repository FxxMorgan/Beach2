<?php
session_start();

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

// Funciones para obtener usuarios y permisos
function obtenerUsuarios($conn) {
    $sql = "SELECT id, email, rol_id FROM usuarios";
    $result = $conn->query($sql);
    if ($result) {
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    return [];
}

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

// Procesar el formulario de actualización de permisos
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['usuario_id'], $_POST['permisos'])) {
    $usuario_id = $_POST['usuario_id'];
    $permisos_seleccionados = $_POST['permisos'];

    // Establecer todos los permisos a 0 inicialmente
    $permisos = [
        'ventas' => 0,
        'sucursales' => 0,
        'gastos' => 0,
        'usuarios' => 0,
        'agregar_trabajador' => 0,
        'sueldos' => 0
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

function actualizarPermisos($conn, $usuario_id, $permisos) {
    foreach ($permisos as $permiso => $estado) {
        $sql_check = "SELECT id FROM permisos WHERE usuario_id = ? AND permiso = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("is", $usuario_id, $permiso);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows > 0) {
            $sql_update = "UPDATE permisos SET estado = ? WHERE usuario_id = ? AND permiso = ?";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("iis", $estado, $usuario_id, $permiso);
            $stmt_update->execute();
            $stmt_update->close();
        } else {
            $sql_insert = "INSERT INTO permisos (usuario_id, permiso, estado) VALUES (?, ?, ?)";
            $stmt_insert = $conn->prepare($sql_insert);
            $stmt_insert->bind_param("isi", $usuario_id, $permiso, $estado);
            $stmt_insert->execute();
            $stmt_insert->close();
        }

        $stmt_check->close();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard TI</title>
        <!-- Favicon -->
    <link rel="icon" href="/images/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/notyf/3.10.0/notyf.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            background-color: #f4f4f9;
        }
        header {
            background-color: #0073e6;
            color: #ffffff;
            padding: 20px;
            text-align: center;
        }
        nav {
            background-color: #343a40;
            padding: 20px 0;
        }
        nav a {
            display: block;
            color: #ffffff;
            padding: 15px;
            text-decoration: none;
            transition: background-color 0.3s;
        }
        nav a:hover {
            background-color: #0073e6;
        }
        main {
            padding: 20px;
            flex-grow: 1;
        }
        footer {
            background-color: #333;
            color: #ffffff;
            text-align: center;
            padding: 10px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: center;
        }
        th {
            background-color: #f2f2f2;
        }
        .btn {
            transition: background-color 0.3s;
        }
        .btn:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <header>
        <h1>Bienvenido al Dashboard TI</h1>
    </header>

    <div class="d-flex">
        <nav class="d-flex flex-column">
            <a href="dashboard.php"><i class="material-icons">check</i> Dashboard</a>
            <a href="dashboard_ti.php"><i class="material-icons">people</i> Dashboard TI</a>
            <a href="gastos.php"><i class="material-icons">monetization_on</i> Gastos</a>
            <a href="sucursales.php"><i class="material-icons">location_city</i> Sucursales</a>
            <a href="sueldos.php"><i class="material-icons">attach_money</i> Sueldos</a>
            <a href="agregar_trabajadores.php"><i class="material-icons">people</i> Trabajadores</a>
            <a href="usuarios.php"><i class="material-icons">person</i> Usuarios</a>
            <a href="ventas.php"><i class="material-icons">shopping_cart</i> Ventas</a>
        </nav>

        <main>
            <h2>Gestión de Permisos</h2>
            <table class="table table-striped table-bordered">
                <thead>
                    <tr>
                        <th>ID Usuario</th>
                        <th>Email</th>
                        <th>Permisos</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $usuario): ?>
                    <?php $permisos = obtenerPermisos($conn, $usuario['id']); ?>
                    <tr>
                        <form method="post">
                            <td><?php echo htmlspecialchars($usuario['id']); ?></td>
                            <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                            <td>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permisos[]" value="ventas" <?php if (isset($permisos['ventas']) && $permisos['ventas'] == 1) echo 'checked'; ?>>
                                    <label class="form-check-label">Ventas</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permisos[]" value="sucursales" <?php if (isset($permisos['sucursales']) && $permisos['sucursales'] == 1) echo 'checked'; ?>>
                                    <label class="form-check-label">Sucursales</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permisos[]" value="gastos" <?php if (isset($permisos['gastos']) && $permisos['gastos'] == 1) echo 'checked'; ?>>
                                    <label class="form-check-label">Gastos</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permisos[]" value="usuarios" <?php if (isset($permisos['usuarios']) && $permisos['usuarios'] == 1) echo 'checked'; ?>>
                                    <label class="form-check-label">Usuarios</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permisos[]" value="agregar_trabajador" <?php if (isset($permisos['agregar_trabajador']) && $permisos['agregar_trabajador'] == 1) echo 'checked'; ?>>
                                    <label class="form-check-label">Agregar Trabajador</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permisos[]" value="sueldos" <?php if (isset($permisos['sueldos']) && $permisos['sueldos'] == 1) echo 'checked'; ?>>
                                    <label class="form-check-label">Sueldos</label>
                                </div>
                            </td>
                            <td>
                                <input type="hidden" name="usuario_id" value="<?php echo htmlspecialchars($usuario['id']); ?>">
                                <button type="submit" class="btn btn-primary">Actualizar</button>
                            </td>
                        </form>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </main>
    </div>

    <footer>
        <p>&copy; 2024 beach. Todos los derechos reservados.</p>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/notyf/3.10.0/notyf.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const notyf = new Notyf();
            notyf.success('Bienvenido al Dashboard TI');
        });
    </script>
</body>
</html>