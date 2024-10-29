<?php
// Procesar el formulario cuando se envía
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Conexión a la base de datos
    $conn = new mysqli('localhost', 'root', '', 'beach2');

    // Verificar la conexión
    if ($conn->connect_error) {
        die("Conexión fallida: " . $conn->connect_error);
    }

    // Obtener datos del formulario
    $nombre = $_POST['nombre'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT); // Usar hash para la contraseña
    $rol_id = $_POST['rol'];
    $sucursal_id = $_POST['sucursal'];

    // Validar campos requeridos
    if (!empty($nombre) && !empty($email) && !empty($password) && !empty($rol_id) && !empty($sucursal_id)) {
        // Insertar datos en la base de datos
        $sql = "INSERT INTO usuarios (nombre, email, contraseña, rol_id, sucursal_id) VALUES ('$nombre', '$email', '$password', '$rol_id', '$sucursal_id')";

        if ($conn->query($sql) === TRUE) {
            echo "<script>Notyf.success('Nuevo usuario creado exitosamente');</script>";
        } else {
            echo "<script>Notyf.error('Error al crear el usuario');</script>";
        }
    } else {
        echo "<script>Notyf.error('Todos los campos son requeridos');</script>";
    }

    // Cerrar la conexión
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios</title>

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">

    <!-- Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">

    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.10.24/css/jquery.dataTables.min.css" rel="stylesheet">

    <!-- Notyf CSS -->
    <link href="https://cdn.jsdelivr.net/npm/notyf/notyf.min.css" rel="stylesheet">
    
    <!-- Material Icons -->
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
            padding: 20px;
        }
        .card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 20px;
            padding: 20px;
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
                        <a href="index.html" class="flex items-center text-white hover:bg-blue-600 p-2 rounded">
                            <span class="material-icons mr-2">dashboard</span>
                            Dashboard
                        </a>
                    </li>
                    <li class="mb-4">
                        <a href="gastos.html" class="flex items-center text-white hover:bg-blue-600 p-2 rounded">
                            <span class="material-icons mr-2">monetization_on</span>
                            Gastos
                        </a>
                    </li>
                    <li class="mb-4">
                        <a href="ventas.php" class="flex items-center text-white hover:bg-blue-600 p-2 rounded">
                            <span class="material-icons mr-2">shopping_cart</span>
                            Ventas
                        </a>
                    </li>
                    <li class="mb-4">
                        <a href="sucursales.html" class="flex items-center text-white hover:bg-blue-600 p-2 rounded">
                            <span class="material-icons mr-2">store</span>
                            Sucursales
                        </a>
                    </li>
                    <li class="mb-4">
                        <a href="usuarios.php" class="flex items-center text-white bg-blue-600 p-2 rounded">
                            <span class="material-icons mr-2">people</span>
                            Usuarios
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="content flex-grow">
            <h1 class="text-3xl font-bold mb-8">Gestión de Usuarios</h1>

            <!-- Formulario para Crear Nuevo Usuario -->
            <div class="card">
                <h2 class="text-xl font-semibold mb-4">Crear Nuevo Usuario</h2>
                <form method="POST" action="usuarios.php">
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
                            // Conexión a la base de datos
                            $conn = new mysqli('localhost', 'root', '', 'beach2');

                            // Verificar la conexión
                            if ($conn->connect_error) {
                                die("Conexión fallida: " . $conn->connect_error);
                            }

                            // Obtener los roles de la base de datos
                            $sql = "SELECT id, nombre FROM roles";
                            $result = $conn->query($sql);

                            if ($result->num_rows > 0) {
                                // Mostrar cada rol como una opción en el select
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
                            // Conexión a la base de datos
                            $conn = new mysqli('localhost', 'root', '', 'beach2');

                            // Verificar la conexión
                            if ($conn->connect_error) {
                                die("Conexión fallida: " . $conn->connect_error);
                            }

                            // Obtener las sucursales de la base de datos
                            $sql = "SELECT id, nombre FROM sucursales";
                            $result = $conn->query($sql);

                            if ($result->num_rows > 0) {
                                // Mostrar cada sucursal como una opción en el select
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

            <!-- Listado de Usuarios -->
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
                        // Conexión a la base de datos
                        $conn = new mysqli('localhost', 'root', '', 'beach2');

                        // Verificar la conexión
                        if ($conn->connect_error) {
                            die("Conexión fallida: " . $conn->connect_error);
                        }

                        // Consulta a la base de datos
                        $sql = "SELECT usuarios.id, usuarios.nombre, usuarios.email, roles.nombre AS rol, sucursales.nombre AS sucursal 
                                FROM usuarios 
                                JOIN roles ON usuarios.rol_id = roles.id 
                                JOIN sucursales ON usuarios.sucursal_id = sucursales.id";
                        $result = $conn->query($sql);

                        if ($result->num_rows > 0) {
                            // Salida de datos de cada fila
                            while($row = $result->fetch_assoc()) {
                                echo "<tr>
                                        <td>{$row['id']}</td>
                                        <td>{$row['nombre']}</td>
                                        <td>{$row['email']}</td>
                                        <td>{$row['rol']}</td>
                                        <td>{$row['sucursal']}</td>
                                        <td>
                                            <button class='bg-yellow-500 text-white px-2 py-1 rounded'>Editar</button>
                                            <button class='bg-red-500 text-white px-2 py-1 rounded'>Eliminar</button>
                                        </td>
                                      </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='6'>No hay usuarios registrados</td></tr>";
                        }

                        $conn->close();
                        ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/notyf/notyf.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', (event) => {
            // Initialize DataTables
            $('#usuariosTable').DataTable();

            // Initialize Notyf for notifications
            const notyf = new Notyf();
        });
    </script>
</body>

</html>
