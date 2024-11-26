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
    $direccion = $_POST['direccion'];
    $rubro = $_POST['rubro'];

    // Validar campos requeridos
    if (!empty($nombre) && !empty($direccion) && !empty($rubro)) {
        // Usar consultas preparadas para evitar inyecciones SQL
        $stmt = $conn->prepare("INSERT INTO sucursales (nombre, direccion, rubro) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $nombre, $direccion, $rubro);

        if ($stmt->execute()) {
            echo "<script>new Notyf().success('Nueva sucursal creada exitosamente');</script>";
        } else {
            echo "<script>new Notyf().error('Error al crear la sucursal');</script>";
        }
        $stmt->close();
    } else {
        echo "<script>new Notyf().error('Todos los campos son requeridos');</script>";
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
    <title>Gestión de Sucursales</title>

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

        <!-- Favicon -->
    <link rel="icon" href="/images/favicon.ico" type="image/x-icon">

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
                        <a href="dashboard.php" class="flex items-center text-white hover:bg-blue-600 p-2 rounded">
                            <span class="material-icons mr-2">dashboard</span>
                            Dashboard
                        </a>
                    </li>
                    <li class="mb-4">
                        <a href="gastos.php" class="flex items-center text-white hover:bg-blue-600 p-2 rounded">
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
                        <a href="sucursales.php" class="flex items-center text-white bg-blue-600 p-2 rounded">
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
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="content flex-grow">
            <h1 class="text-3xl font-bold mb-8">Gestión de Sucursales</h1>

            <!-- Formulario para Crear Nueva Sucursal -->
            <div class="card">
                <h2 class="text-xl font-semibold mb-4">Crear Nueva Sucursal</h2>
                <form method="POST" action="sucursales.php">
                    <div class="mb-4">
                        <label for="nombre" class="block text-sm font-medium text-gray-700">Nombre</label>
                        <input type="text" name="nombre" id="nombre" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div class="mb-4">
                        <label for="direccion" class="block text-sm font-medium text-gray-700">Dirección</label>
                        <input type="text" name="direccion" id="direccion" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div class="mb-4">
                        <label for="rubro" class="block text-sm font-medium text-gray-700">Rubro</label>
                        <select name="rubro" id="rubro" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="ferreteria">Ferretería</option>
                            <option value="multitienda">Multitienda</option>
                            <option value="supermercado">Supermercado</option>
                            <option value="cabaña">Cabaña</option>
                        </select>
                    </div>
                    <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">Crear Sucursal</button>
                </form>
            </div>

            <!-- Listado de Sucursales -->
            <div class="card">
                <h2 class="text-xl font-semibold mb-4">Listado de Sucursales</h2>
                <table id="sucursalesTable" class="w-full">
                    <thead>
                        <tr>
                            <th class="text-left">ID</th>
                            <th class="text-left">Nombre</th>
                            <th class="text-left">Dirección</th>
                            <th class="text-left">Rubro</th>
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
                        $sql = "SELECT id, nombre, direccion, rubro FROM sucursales";
                        $result = $conn->query($sql);

                        if ($result->num_rows > 0) {
                            // Salida de datos de cada fila
                            while($row = $result->fetch_assoc()) {
                                echo "<tr>
                                        <td>{$row['id']}</td>
                                        <td>{$row['nombre']}</td>
                                        <td>{$row['direccion']}</td>
                                        <td>{$row['rubro']}</td>
                                        <td>
                                            <button class='bg-yellow-500 text-white px-2 py-1 rounded'>Editar</button>
                                            <button class='bg-red-500 text-white px-2 py-1 rounded'>Eliminar</button>
                                        </td>
                                      </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='5'>No hay sucursales registradas</td></tr>";
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
            $('#sucursalesTable').DataTable();

            // Initialize Notyf for notifications
            const notyf = new Notyf();
        });
    </script>
</body>

</html>
