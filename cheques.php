<?php
session_start();

// Redirigir si el usuario no ha iniciado sesión
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['sucursal_id']) || !isset($_SESSION['rol_id'])) {
    header('Location: login.php');
    exit();
}

date_default_timezone_set('America/Santiago'); // Ajusta a tu zona horaria

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

// Inicializar variables para mensajes
$mensaje_exito = '';
$mensaje_error = '';

// Procesar el formulario al recibir una solicitud POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['crear_cheque'])) {
        // Obtener y sanitizar los datos del formulario
        $descripcion = $conn->real_escape_string($_POST['descripcion']);
        $monto = floatval($_POST['monto']);
        $fecha_pago = $_POST['fecha_pago'];

        // Validar que la fecha tenga el formato correcto y convertirla
        $fecha_pago_formateada = date('Y-m-d', strtotime($fecha_pago));

        if (!empty($descripcion) && is_numeric($monto) && !empty($fecha_pago_formateada)) {
            $fecha_actual = date('Y-m-d');

            // Determinar el estado basado en la fecha de pago
            $estado = ($fecha_pago_formateada > $fecha_actual) ? 'pendiente' : 'listo';

            // Preparar la consulta para insertar el cheque
            $sql_crear_cheque = "INSERT INTO cheques (descripcion, monto, fecha_pago, sucursal_id, estado) VALUES (?, ?, ?, ?, ?)";
            $stmt_crear_cheque = $conn->prepare($sql_crear_cheque);
            $stmt_crear_cheque->bind_param("sdsis", $descripcion, $monto, $fecha_pago_formateada, $sucursal_id, $estado);

            // Ejecutar la consulta y verificar el resultado
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

    // Lógica para suscribirse a alertas (si corresponde)
    if (isset($_POST['suscribir_alerta'])) {
        // Obtener el correo electrónico del usuario
        $sql_email = "SELECT email FROM usuarios WHERE id = ?";
        $stmt_email = $conn->prepare($sql_email);
        $stmt_email->bind_param("i", $usuario_id);
        $stmt_email->execute();
        $result_email = $stmt_email->get_result();
        $usuario = $result_email->fetch_assoc();
        $email_usuario = $usuario['email'];
        $stmt_email->close();

        // Mensaje de éxito
        $mensaje_exito = "Te has suscrito a las alertas exitosamente.";
    }
}

// Actualizar el estado de los cheques a 'listo' si corresponde
$fecha_actual = date('Y-m-d');
$sql_actualizar_cheques = "UPDATE cheques SET estado = 'listo' WHERE sucursal_id = ? AND fecha_pago <= ? AND estado = 'pendiente'";
$stmt_actualizar_cheques = $conn->prepare($sql_actualizar_cheques);
$stmt_actualizar_cheques->bind_param("is", $sucursal_id, $fecha_actual);
$stmt_actualizar_cheques->execute();
$stmt_actualizar_cheques->close();

// Obtener cheques pendientes
$sql_cheques_pendientes = "SELECT id, descripcion, monto, fecha_pago, estado FROM cheques WHERE sucursal_id = ? AND estado = 'pendiente'";
$stmt_cheques_pendientes = $conn->prepare($sql_cheques_pendientes);
$stmt_cheques_pendientes->bind_param("i", $sucursal_id);
$stmt_cheques_pendientes->execute();
$result_cheques_pendientes = $stmt_cheques_pendientes->get_result();
$cheques_pendientes = $result_cheques_pendientes->fetch_all(MYSQLI_ASSOC);
$stmt_cheques_pendientes->close();

// Obtener cheques listos
$sql_cheques_listos = "SELECT id, descripcion, monto, fecha_pago, estado FROM cheques WHERE sucursal_id = ? AND estado = 'listo'";
$stmt_cheques_listos = $conn->prepare($sql_cheques_listos);
$stmt_cheques_listos->bind_param("i", $sucursal_id);
$stmt_cheques_listos->execute();
$result_cheques_listos = $stmt_cheques_listos->get_result();
$cheques_listos = $result_cheques_listos->fetch_all(MYSQLI_ASSOC);
$stmt_cheques_listos->close();

// Cerrar la conexión
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>

    <link rel="icon" href="/images/favicon.ico" type="image/x-icon">
    <!-- Cargar jQuery primero -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/1.7.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/1.7.1/js/buttons.html5.min.js"></script>

    <link href="https://fonts.googleapis/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.10.24/css/jquery.dataTables.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/1.7.1/css/buttons.dataTables.min.css" rel="stylesheet">
    
    <style>
        /* General */
        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
            display: flex;
            flex-direction: column;
            font-family: 'Roboto', sans-serif;
            background-color: #f4f6f9;
        }

        .flex {
            display: flex;
            width: 100%;
            height: 100%;
        }

        /* Barra lateral */
        .sidebar {
            background-color: #2c3e50;
            color: #ecf0f1;
            width: 250px;
            padding-top: 2rem;
            height: 100vh;
            overflow-y: auto;
            position: fixed;
        }

        /* Contenido principal */
        .main-content {
            margin-left: 250px;
            padding: 1rem;
            width: calc(100% - 250px);
            display: flex;
            flex-direction: row;
            gap: 1rem;
        }

        /* Columnas de contenido */
        .left-column, .right-column {
            flex: 1;
            min-width: 0;
        }

        /* Tarjetas y Tablas */
        .card {
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 1rem;
            margin-bottom: 1rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table th, table td {
            padding: 0.5rem;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        table th {
            background-color: #f1f5f9;
            font-weight: 600;
        }

        .dataTables_wrapper .dataTables_paginate {
            padding: 1rem;
            text-align: center;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 0.5rem 1rem;
            margin: 0 0.1rem;
            border: 1px solid #007bff;
            border-radius: 4px;
            background-color: #ffffff;
            color: #007bff;
            transition: background-color 0.3s, color 0.3s;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background-color: #007bff;
            color: white;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background-color: #007bff;
            color: white;
            border: 1px solid #007bff;
        }

        .dataTables_wrapper .dataTables_filter {
            margin: 1rem 0;
            text-align: right;
        }

        .dataTables_wrapper .dataTables_filter label {
            font-weight: bold;
            margin-right: 0.5rem;
        }

        .dataTables_wrapper .dataTables_filter input[type="search"] {
            padding: 0.5rem;
            border: 2px solid #007bff; /* Borde más grueso */
            border-radius: 4px;
            outline: none;
            transition: border-color 0.3s, box-shadow 0.3s;
            width: 250px; /* Ancho fijo o ajustable */
        }

        .dataTables_wrapper .dataTables_filter input[type="search"]:focus {
            border-color: #0056b3; /* Color al hacer foco */
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.5); /* Sombra al hacer foco */
        }

        /* Botones */
        button, input[type="submit"] {
            background-color: #007bff; /* Azul más brillante */
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        button:hover, input[type="submit"]:hover {
            background-color: #0056b3; /* Azul más oscuro al pasar el cursor */
        }

        /* Menú desplegable */
        .dropdown-toggle {
            cursor: pointer;
            display: flex;
            align-items: center;
        }

        .dropdown-menu {
            display: none;
            padding-left: 1.5rem;
        }

        .dropdown-toggle.active + .dropdown-menu {
            display: block;
        }

        /* Media query para pantallas pequeñas */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
                width: 100%;
            }
            .sidebar {
                width: 100%;
                position: relative;
                height: auto;
            }
        }
    </style>
</head>

<body>
    <div class="flex">
        <aside class="sidebar">
            <div class="mb-8">
                <img src="../../images/logo.png" alt="Logo" class="w-32 mx-auto">
            </div>
            <nav>
                <ul>
                    <li class="mb-4">
                        <a id="dashboard-link" href="dashboard_jefe.php" class="flex items-center text-white hover:bg-blue-600 p-2 rounded">
                            <span class="material-icons mr-2">dashboard</span>
                            Dashboard
                        </a>
                    </li>
                    <li class="mb-4">
                        <a id="cheques-link" href="cheques.php" class="flex items-center text-white hover:bg-blue-600 p-2 rounded">
                            <span class="material-icons mr-2">account_balance</span>
                            Cheques
                        </a>
                    </li>
                    <li class="mb-4">
                        <a id="gastos-toggle" href="#" class="flex items-center text-white hover:bg-blue-600 p-2 rounded dropdown-toggle">
                            <span class="material-icons mr-2">payments</span>
                            Gastos
                            <span class="material-icons ml-auto">arrow_drop_down</span>
                        </a>
                        <ul class="ml-4 mt-2 dropdown-menu">
                            <li>
                                <a href="sueldos.php" class="flex items-center text-white hover:bg-blue-500 p-2 rounded">
                                    <span class="material-icons mr-2">attach_money</span>
                                    Sueldos
                                </a>
                            </li>
                            <li class="mb-2">
                                <a href="agregar_trabajadores.php" class="flex items-center text-white hover:bg-blue-500 p-2 rounded">
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
                        <a id="ventas-link" href="ventas.php" class="flex items-center text-white hover:bg-blue-600 p-2 rounded">
                            <span class="material-icons mr-2">shopping_cart</span>
                            Ventas
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <div class="left-column">
                <h1 class="text-2xl font-bold mb-4">Gestión de Cheques</h1>

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

                <div class="card mb-6">
                    <h2 class="text-xl font-semibold mb-4">Crear Cheque</h2>
                    <form method="POST" action="cheques.php">
                        <div class="mb-4">
                            <label for="descripcion" class="block mb-1">Descripción</label>
                            <input type="text" name="descripcion" id="descripcion" required class="border p-2 w-full">
                        </div>
                        <div class="mb-4">
                            <label for="monto" class="block mb-1">Monto</label>
                            <input type="number" name="monto" id="monto" step="0.01" required class="border p-2 w-full">
                        </div>
                        <div class="mb-4">
                            <label for="fecha_pago" class="block mb-1">Fecha de Pago</label>
                            <input type="date" name="fecha_pago" id="fecha_pago" required class="border p-2 w-full">
                        </div>
                        <button type="submit" name="crear_cheque" class="bg-blue-600 text-white rounded py-2 px-4">Crear Cheque</button>
                    </form>
                </div>

                <div class="card mb-6">
                    <h2 class="text-xl font-semibold mb-4">Suscribirse a Alertas</h2>
                    <form method="POST">
                        <button type="submit" name="suscribir_alerta" class="bg-blue-600 text-white rounded py-2 px-4">Suscribirse</button>
                    </form>
                </div>
            </div>

            <div class="right-column">
    <div class="card p-6 mb-4">
        <h2 class="text-xl font-semibold mb-4">Cheques Pendientes</h2>
        <table id="chequesPendientesTable" class="min-w-full">
            <thead>
                <tr>
                    <th class="border px-4 py-2">Descripción</th>
                    <th class="border px-4 py-2">Monto</th>
                    <th class="border px-4 py-2">Fecha de Pago</th>
                    <th class="border px-4 py-2">Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cheques_pendientes as $cheque): ?>
                <tr>
                    <td class="border px-4 py-2"><?php echo htmlspecialchars($cheque['descripcion']); ?></td>
                    <td class="border px-4 py-2"><?php echo number_format($cheque['monto'], 2); ?></td>
                    <td class="border px-4 py-2"><?php echo htmlspecialchars($cheque['fecha_pago']); ?></td>
                    <td class="border px-4 py-2"><?php echo htmlspecialchars($cheque['estado']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card p-6">
        <h2 class="text-xl font-semibold mb-4">Cheques Listos</h2>
        <table id="chequesListosTable" class="min-w-full">
            <thead>
                <tr>
                    <th class="border px-4 py-2">Descripción</th>
                    <th class="border px-4 py-2">Monto</th>
                    <th class="border px-4 py-2">Fecha de Pago</th>
                    <th class="border px-4 py-2">Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cheques_listos as $cheque): ?>
                <tr>
                    <td class="border px-4 py-2"><?php echo htmlspecialchars($cheque['descripcion']); ?></td>
                    <td class="border px-4 py-2"><?php echo number_format($cheque['monto'], 2); ?></td>
                    <td class="border px-4 py-2"><?php echo htmlspecialchars($cheque['fecha_pago']); ?></td>
                    <td class="border px-4 py-2"><?php echo htmlspecialchars($cheque['estado']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>


    <script>
$(document).ready(function() {
    $('#chequesPendientesTable').DataTable({
        dom: 'Bfrtip',
        buttons: [
            {
                extend: 'excelHtml5',
                title: 'Cheques Pendientes',
                text: 'Exportar a Excel',
                className: 'btn-export',
                exportOptions: {
                    format: {
                        body: function(data, row, column, node) {
                            if (column === 1) { // Columna "Monto"
                                // Limpia los símbolos y formatea con punto como separador de miles
                                let numericValue = parseFloat(data.replace(/[^\d.-]/g, ''));
                                if (!isNaN(numericValue)) {
                                    return numericValue.toLocaleString('de-DE', { minimumFractionDigits: 0 });
                                }
                            }
                            return data;
                        }
                    }
                }
            }
        ],
        language: { /* configuración de lenguaje aquí */ },
        columnDefs: [
            {
                targets: 1, // Índice de la columna "Monto"
                render: function(data, type, row) {
                    let numericValue = parseFloat(data.replace(/[^\d.-]/g, ''));
                    if (isNaN(numericValue)) return data;
                    return new Intl.NumberFormat('es-CL', {
                        style: 'currency',
                        currency: 'CLP',
                        minimumFractionDigits: 0
                    }).format(numericValue);
                }
            }
        ]
    });

    $('#chequesListosTable').DataTable({
        dom: 'Bfrtip',
        buttons: [
            {
                extend: 'excelHtml5',
                title: 'Cheques Listos',
                text: 'Exportar a Excel',
                className: 'btn-export',
                exportOptions: {
                    format: {
                        body: function(data, row, column, node) {
                            if (column === 1) { // Columna "Monto"
                                let numericValue = parseFloat(data.replace(/[^\d.-]/g, ''));
                                if (!isNaN(numericValue)) {
                                    return numericValue.toLocaleString('de-DE', { minimumFractionDigits: 0 });
                                }
                            }
                            return data;
                        }
                    }
                }
            }
        ],
        language: { /* configuración de lenguaje aquí */ },
        columnDefs: [
            {
                targets: 1, // Índice de la columna "Monto"
                render: function(data, type, row) {
                    let numericValue = parseFloat(data.replace(/[^\d.-]/g, ''));
                    if (isNaN(numericValue)) return data;
                    return new Intl.NumberFormat('es-CL', {
                        style: 'currency',
                        currency: 'CLP',
                        minimumFractionDigits: 0
                    }).format(numericValue);
                }
            }
        ]
    });
        // Dropdown toggle
        $('.dropdown-toggle').click(function() {
            $(this).toggleClass('active');
        });

    });
</script>


</body>
</html>

