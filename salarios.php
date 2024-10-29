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

// Obtener trabajadores de la sucursal del usuario
function obtenerTrabajadoresSucursal($conn, $sucursal_id) {
    $sql = "SELECT id, nombre, puesto FROM trabajadores WHERE sucursal_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $sucursal_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $trabajadores = [];
    while ($row = $result->fetch_assoc()) {
        $trabajadores[] = $row;
    }
    $stmt->close(); // Cerramos el statement aquí
    return $trabajadores;
}

$trabajadores = obtenerTrabajadoresSucursal($conn, $sucursal_id);

// Manejar la solicitud de registro de salario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $trabajador_id = $_POST['trabajador_id'];
    $salario = $_POST['salario'];
    $fecha = $_POST['fecha'];

    // Verificar que el trabajador pertenece a la sucursal del usuario
    $sql = "SELECT sucursal_id FROM trabajadores WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $trabajador_id);
    $stmt->execute();
    $stmt->bind_result($trabajador_sucursal_id);
    $stmt->fetch();

    // Verificamos si el trabajador pertenece a la sucursal
    if ($trabajador_sucursal_id !== $sucursal_id) {
        echo "No tienes permiso para registrar información para este trabajador.";
        $stmt->close(); // Cerramos el statement aquí antes de salir
        exit();
    }
    $stmt->close(); // Cerramos el statement aquí para evitar conflictos

    // Insertar el salario en la base de datos
    $sql = "INSERT INTO sueldos (trabajador_id, monto, fecha_pago) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ids", $trabajador_id, $salario, $fecha);

    if ($stmt->execute()) {
        echo "Salario registrado exitosamente.";
    } else {
        echo "Error al registrar el salario.";
    }
    $stmt->close(); // Cerramos el statement después de ejecutar la inserción
}

// Consulta para obtener el historial de salarios
$sql = "SELECT sueldos.id, trabajadores.nombre AS empleado, sueldos.monto, sueldos.fecha_pago 
        FROM sueldos 
        JOIN trabajadores ON sueldos.trabajador_id = trabajadores.id
        WHERE trabajadores.sucursal_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $sucursal_id);
$stmt->execute();
$result = $stmt->get_result();

// Almacenar el historial de salarios en una variable
$historial_salarios = [];
while ($row = $result->fetch_assoc()) {
    $historial_salarios[] = $row;
}
$stmt->close(); // Cerrar el statement para la consulta de salarios

?>



<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Salarios</title>

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
                        <a href="index.html" class="flex items-center text-white hover:bg-blue-600 p-2 rounded">
                            <span class="material-icons mr-2">dashboard</span>
                            Dashboard
                        </a>
                    </li>
                    <li class="mb-4">
                        <a href="salarios.php" class="flex items-center text-white hover:bg-blue-600 bg-blue-600 p-2 rounded">
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
            <h1 class="text-3xl font-bold mb-8">Registro de Salarios</h1>

            <!-- Formulario para Registrar Salario -->
            <div class="card">
                <h2 class="text-xl font-semibold mb-4">Registrar Nuevo Salario</h2>
                <form method="POST" action="salarios.php">
                    <div class="mb-4">
                        <label for="trabajador_id" class="block text-sm font-medium text-gray-700">Empleado</label>
                        <select name="trabajador_id" id="trabajador_id" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <?php
                            // Genera las opciones para los trabajadores de la sucursal
                            foreach ($trabajadores as $trabajador) {
                                echo "<option value='{$trabajador['id']}'>{$trabajador['nombre']} - {$trabajador['puesto']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label for="salario" class="block text-sm font-medium text-gray-700">Monto</label>
                        <input type="number" name="salario" id="salario" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div class="mb-4">
                        <label for="fecha" class="block text-sm font-medium text-gray-700">Fecha de Pago</label>
                        <input type="date" name="fecha" id="fecha" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">Registrar Salario</button>
                </form>
            </div>

            <!-- Historial de Salarios -->
            <div class="card">
                <h2 class="text-xl font-semibold mb-4">Historial de Salarios</h2>
                <table id="salariosTable" class="w-full">
                    <thead>
                        <tr>
                            <th class="text-left">ID</th>
                            <th class="text-left">Empleado</th>
                            <th class="text-left">Monto</th>
                            <th class="text-left">Fecha de Pago</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT sueldos.id, trabajadores.nombre AS empleado, sueldos.monto, sueldos.fecha_pago 
                                FROM sueldos 
                                JOIN trabajadores ON sueldos.trabajador_id = trabajadores.id
                                WHERE trabajadores.sucursal_id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("i", $sucursal_id);
                        $stmt->execute();
                        $result = $stmt->get_result();

                        while ($row = $result->fetch_assoc()) {
                            echo "<tr>
                                    <td>{$row['id']}</td>
                                    <td>{$row['empleado']}</td>
                                    <td>{$row['monto']}</td>
                                    <td>{$row['fecha_pago']}</td>
                                  </tr>";
                        }
                        $stmt->close();
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
        document.addEventListener('DOMContentLoaded', () => {
            $('#salariosTable').DataTable();
            const notyf = new Notyf();
        });
    </script>
</body>
</html>



