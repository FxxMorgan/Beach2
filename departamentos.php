<?php
session_start();

// Verificar sesión y rol
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['sucursal_id']) || !isset($_SESSION['rol_id']) || $_SESSION['rol_id'] != 2) {
    header('Location: login.php');
    exit();
}

try {
    // Conexión a la base de datos
    $conn = new mysqli('localhost', 'root', '', 'beach2');
    if ($conn->connect_error) {
        throw new Exception("Conexión fallida: " . $conn->connect_error);
    }

    // Manejo de formularios
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (isset($_POST['add_department'])) {
            $department_name = $conn->real_escape_string($_POST['department_name']);
            $sql = "INSERT INTO departamentos (nombre) VALUES ('$department_name')";
            if ($conn->query($sql) === FALSE) {
                echo "Error al agregar departamento: " . $conn->error;
            }
        } elseif (isset($_POST['add_expense'])) {
            $department_id = $_POST['department_id'];
            $expense_type = $conn->real_escape_string($_POST['expense_type']);
            $amount = $_POST['amount'];

            // Validación simple
            if (is_numeric($amount) && $amount > 0) {
                $stmt = $conn->prepare("INSERT INTO gastos (departamento_id, gasto_tipo, monto) VALUES (?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param("isi", $department_id, $expense_type, $amount);
                    if (!$stmt->execute()) {
                        echo "Error al agregar gasto: " . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    echo "Error en la preparación de la consulta: " . $conn->error;
                }
            } else {
                echo "Monto debe ser un número positivo.";
            }
        }
    }

    // Obtener departamentos
    $departments = $conn->query("SELECT * FROM departamentos");

    // Obtener gastos
    $expenses = $conn->query("SELECT g.*, d.nombre AS departamento FROM gastos g JOIN departamentos d ON g.departamento_id = d.id");

    $conn->close();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Administrar Departamentos</title>
        <!-- Favicon -->
        <link rel="icon" href="/images/favicon.ico" type="image/x-icon">
</head>
<body>
    <div class="container">
        <h1>Administrar Departamentos</h1>

        <h2>Agregar Departamento</h2>
        <form method="POST">
            <input type="text" name="department_name" placeholder="Nombre del Departamento" required>
            <button type="submit" name="add_department">Agregar</button>
        </form>

        <h2>Ingresar Gasto</h2>
        <form method="POST">
            <select name="department_id" required>
                <option value="">Seleccionar Departamento</option>
                <?php while ($row = $departments->fetch_assoc()): ?>
                    <option value="<?php echo $row['id']; ?>"><?php echo $row['nombre']; ?></option>
                <?php endwhile; ?>
            </select>
            <input type="text" name="expense_type" placeholder="Tipo de Gasto" required>
            <input type="number" name="amount" placeholder="Monto" required>
            <button type="submit" name="add_expense">Agregar Gasto</button>
        </form>

        <h2>Gastos Registrados</h2>
        <table>
            <thead>
                <tr>
                    <th>Departamento</th>
                    <th>Tipo de Gasto</th>
                    <th>Monto</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $expenses->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['departamento']); ?></td>
                        <td><?php echo htmlspecialchars($row['gasto_tipo']); ?></td> <!-- Cambiado de 'tipo' a 'gasto_tipo' -->
                        <td><?php echo htmlspecialchars(number_format($row['monto'], 2)); ?></td> <!-- Formatear el monto -->
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
