<?php
session_start();

// Procesar el formulario cuando se envía
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $conn = new mysqli('localhost', 'root', '', 'beach2');
    if ($conn->connect_error) {
        die("Conexión fallida: " . $conn->connect_error);
    }

    if (isset($_POST['accion'])) {
        $accion = $_POST['accion'];
        
        if ($accion == 'crear') {
            // Obtener datos del formulario
            $nombre = $_POST['nombre'];
            $email = $_POST['email'];
            $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
            $rol_id = $_POST['rol'];
            $sucursal_id = $_POST['sucursal'];

            if (!empty($nombre) && !empty($email) && !empty($password) && !empty($rol_id) && !empty($sucursal_id)) {
                // Insertar datos en la base de datos
                $sql = "INSERT INTO usuarios (nombre, email, contraseña, rol_id, sucursal_id) VALUES ('$nombre', '$email', '$password', '$rol_id', '$sucursal_id')";
                if ($conn->query($sql) === TRUE) {
                    echo "<script>alert('Nuevo usuario creado exitosamente');</script>";
                } else {
                    echo "<script>alert('Error al crear el usuario');</script>";
                }
            } else {
                echo "<script>alert('Todos los campos son requeridos');</script>";
            }
        } elseif ($accion == 'eliminar') {
                $id = $_POST['id'];
            
                // Primero, desvincula el usuario en la tabla trabajadores
                $sql_trabajadores = "UPDATE `trabajadores` SET `usuario_id` = NULL WHERE `usuario_id` = ?";
                $stmt_trabajadores = $conn->prepare($sql_trabajadores);
                if ($stmt_trabajadores) {
                    $stmt_trabajadores->bind_param("i", $id);
                    $stmt_trabajadores->execute();
                    $stmt_trabajadores->close();
                }
            
                // Luego, desvincula el usuario en la tabla sueldos
                $sql_sueldos = "UPDATE `sueldos` SET `usuario_id` = NULL WHERE `usuario_id` = ?";
                $stmt_sueldos = $conn->prepare($sql_sueldos);
                if ($stmt_sueldos) {
                    $stmt_sueldos->bind_param("i", $id);
                    $stmt_sueldos->execute();
                    $stmt_sueldos->close();
                }
            
                // Desvincula el usuario en la tabla permisos
                $sql_permisos = "UPDATE `permisos` SET `usuario_id` = NULL WHERE `usuario_id` = ?";
                $stmt_permisos = $conn->prepare($sql_permisos);
                if ($stmt_permisos) {
                    $stmt_permisos->bind_param("i", $id);
                    $stmt_permisos->execute();
                    $stmt_permisos->close();
                }
            
                // Finalmente, elimina el usuario
                $sql_usuario = "DELETE FROM `usuarios` WHERE `id` = ?";
                $stmt_usuario = $conn->prepare($sql_usuario);
                if ($stmt_usuario) {
                    $stmt_usuario->bind_param("i", $id);
                    if ($stmt_usuario->execute()) {
                        if ($stmt_usuario->affected_rows > 0) {
                            echo "<script>alert('Usuario eliminado exitosamente');</script>";
                        } else {
                            echo "<script>alert('No se encontró el usuario para eliminar');</script>";
                        }
                    } else {
                        echo "<script>alert('Error al ejecutar la consulta de usuario: " . $stmt_usuario->error . "');</script>";
                    }
                    $stmt_usuario->close();
                } else {
                    echo "<script>alert('Error al preparar la consulta de eliminación de usuario');</script>";
                }
            }
            
            $conn->close();
            
    }
}
?>        

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios</title>
        <!-- Favicon -->
        <link rel="icon" href="/images/favicon.ico" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.10.24/css/jquery.dataTables.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        body { font-family: 'Roboto', sans-serif; background-color: #f4f6f9; }
        .sidebar { background-color: #2c3e50; color: #ecf0f1; }
        .content { margin-left: 250px; padding: 20px; }
        .card { background-color: white; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); overflow: hidden; margin-bottom: 20px; padding: 20px; }
    </style>
</head>

<body>
    <div class="flex">
        <aside class="sidebar fixed h-screen w-64 p-4">
            <div class="mb-8">
                <img src="../../images/logo.png" alt="Logo" class="w-32 mx-auto">
            </div>
            <nav>
                <ul>
                    <li class="mb-4"><a href="dashboard.php" class="flex items-center text-white hover:bg-blue-600 p-2 rounded"><span class="material-icons mr-2">dashboard</span>Dashboard</a></li>
                    <li class="mb-4"><a href="gastos.php" class="flex items-center text-white hover:bg-blue-600 p-2 rounded"><span class="material-icons mr-2">monetization_on</span>Gastos</a></li>
                    <li class="mb-4"><a href="ventas.php" class="flex items-center text-white hover:bg-blue-600 p-2 rounded"><span class="material-icons mr-2">shopping_cart</span>Ventas</a></li>
                    <li class="mb-4"><a href="sucursales.php" class="flex items-center text-white hover:bg-blue-600 p-2 rounded"><span class="material-icons mr-2">store</span>Sucursales</a></li>
                    <li class="mb-4"><a href="usuarios.php" class="flex items-center text-white bg-blue-600 p-2 rounded"><span class="material-icons mr-2">people</span>Usuarios</a></li>
                </ul>
            </nav>
        </aside>

        <main class="content flex-grow">
            <h1 class="text-3xl font-bold mb-8">Gestión de Usuarios</h1>

            <div class="card">
                <h2 class="text-xl font-semibold mb-4">Crear Nuevo Usuario</h2>
                <form method="POST" action="usuarios.php">
                    <input type="hidden" name="accion" value="crear">
                    <div class="mb-4">
                        <label for="nombre" class="block text-sm font-medium text-gray-700">Nombre</label>
                        <input type="text" name="nombre" id="nombre" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div class="mb-4">
                        <label for="email" class="block text-sm font-medium text-gray-700">Correo Electrónico</label>
                        <input type="email" name="email" id="email" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div class="mb-4">
                        <label for="password" class="block text-sm font-medium text-gray-700">Contraseña</label>
                        <input type="password" name="password" id="password" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div class="mb-4">
                        <label for="rol" class="block text-sm font-medium text-gray-700">Rol</label>
                        <select name="rol" id="rol" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <?php
                            $conn = new mysqli('localhost', 'root', '', 'beach2');
                            if ($conn->connect_error) {
                                die("Conexión fallida: " . $conn->connect_error);
                            }
                            $sql = "SELECT id, nombre FROM roles";
                            $result = $conn->query($sql);
                            if ($result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    echo "<option value='{$row['id']}'>{$row['nombre']}</option>";
                                }
                            } else {
                                echo "<option value=''>No hay roles disponibles</option>";
                            }
                            $conn->close();
                            ?>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label for="sucursal" class="block text-sm font-medium text-gray-700">Sucursal</label>
                        <select name="sucursal" id="sucursal" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <?php
                            $conn = new mysqli('localhost', 'root', '', 'beach2');
                            if ($conn->connect_error) {
                                die("Conexión fallida: " . $conn->connect_error);
                            }
                            $sql = "SELECT id, nombre FROM sucursales";
                            $result = $conn->query($sql);
                            if ($result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    echo "<option value='{$row['id']}'>{$row['nombre']}</option>";
                                }
                            } else {
                                echo "<option value=''>No hay sucursales disponibles</option>";
                            }
                            $conn->close();
                            ?>
                        </select>
                    </div>
                    <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">Crear Usuario</button>
                </form>
            </div>

            <div class="card">
                <h2 class="text-xl font-semibold mb-4">Listado de Usuarios</h2>
                <table id="usuariosTable" class="w-full">
                    <thead>
                        <tr>
                            <th class="text-left">ID</th>
                            <th class="text-left">Nombre</th>
                            <th class="text-left">Email</th>
                            <th class="text-left">Rol</th>
                            <th class="text-left">Sucursal</th>
                            <th class="text-left">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $conn = new mysqli('localhost', 'root', '', 'beach2');
                        if ($conn->connect_error) {
                            die("Conexión fallida: " . $conn->connect_error);
                        }
                        $sql = "SELECT usuarios.id, usuarios.nombre, usuarios.email, roles.nombre AS rol, sucursales.nombre AS sucursal 
                                FROM usuarios 
                                JOIN roles ON usuarios.rol_id = roles.id 
                                JOIN sucursales ON usuarios.sucursal_id = sucursales.id";
                        $result = $conn->query($sql);
                        if ($result->num_rows > 0) {
                            while($row = $result->fetch_assoc()) {
                                echo "<tr>
                                        <td>{$row['id']}</td>
                                        <td>{$row['nombre']}</td>
                                        <td>{$row['email']}</td>
                                        <td>{$row['rol']}</td>
                                        <td>{$row['sucursal']}</td>
                                        <td>
                                            <form method='POST' action='usuarios.php' style='display:inline;'>
                                                <input type='hidden' name='accion' value='eliminar'>
                                                <input type='hidden' name='id' value='{$row['id']}'>
                                                <button type='submit' class='bg-red-500 text-white px-2 py-1 rounded'>Eliminar</button>
                                            </form>
                                            <button class='bg-yellow-500 text-white px-2 py-1 rounded'>Editar</button>
                                        </td>
                                      </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='6'>No hay usuarios disponibles</td></tr>";
                        }
                        $conn->close();
                        ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#usuariosTable').DataTable();
        });
    </script>
</body>
</html>
