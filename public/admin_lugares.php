<?php
/**
 * public/admin_lugares.php
 * Gestión Maestra de Ubicaciones - Diseño Moderno
 */
require_once '../core/db.php';
require_once '../core/session.php';

// Seguridad: Solo Admin
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'Administrador') {
    header('Location: dashboard.php');
    exit;
}

$msg = "";
$editMode = false;
$dataEdit = ['id' => '', 'sede' => '', 'nombre' => ''];

// --- PROCESAR POST (Crear / Editar) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $sede = $_POST['sede'] ?? '';
    $nombre = trim($_POST['nombre'] ?? '');

    try {
        if ($action == 'add' && !empty($nombre)) {
            $stmt = $pdo->prepare("INSERT INTO lugares (sede, nombre, estado) VALUES (?, ?, 1)");
            $stmt->execute([$sede, $nombre]);
            $msg = "<div class='toast success'>✅ Ubicación agregada exitosamente.</div>";
        } 
        elseif ($action == 'edit' && !empty($nombre)) {
            $id = $_POST['id'];
            $stmt = $pdo->prepare("UPDATE lugares SET sede = ?, nombre = ? WHERE id = ?");
            $stmt->execute([$sede, $nombre, $id]);
            $msg = "<div class='toast success'>✏️ Ubicación renombrada correctamente.</div>";
        }
    } catch (PDOException $e) {
        $msg = "<div class='toast error'>❌ Error DB: " . $e->getMessage() . "</div>";
    }
}

// --- PROCESAR GET (Eliminar / Toggle / Cargar Edición) ---
if (isset($_GET['action'])) {
    $id = (int)$_GET['id'];
    
    // 1. ELIMINAR (DELETE)
    if ($_GET['action'] == 'delete') {
        try {
            $stmt = $pdo->prepare("DELETE FROM lugares WHERE id = ?");
            $stmt->execute([$id]);
            $msg = "<div class='toast success'>🗑️ Ubicación eliminada permanentemente.</div>";
        } catch (PDOException $e) {
            // Error 23000 es violación de integridad (Foreign Key)
            if ($e->getCode() == '23000') {
                $msg = "<div class='toast warning'>⚠️ No se puede eliminar: Este edificio ya tiene historial en la bitácora. <br>Sugerencia: Usa el botón de estado para desactivarlo.</div>";
            } else {
                $msg = "<div class='toast error'>Error: " . $e->getMessage() . "</div>";
            }
        }
    }
    
    // 2. CAMBIAR ESTADO (TOGGLE)
    if ($_GET['action'] == 'toggle') {
        $stmt = $pdo->prepare("UPDATE lugares SET estado = NOT estado WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: admin_lugares.php"); 
        exit;
    }
    
    // 3. CARGAR PARA EDICIÓN
    if ($_GET['action'] == 'edit') {
        $stmt = $pdo->prepare("SELECT * FROM lugares WHERE id = ?");
        $stmt->execute([$id]);
        $dataEdit = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($dataEdit) $editMode = true;
    }
}

// Consultar todos
$lugares = $pdo->query("SELECT * FROM lugares ORDER BY sede ASC, nombre ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Ubicaciones</title>
    <style>
        :root {
            --primary: #002D72;
            --accent: #ffc107;
            --text: #333;
            --bg: #f0f2f5;
            --white: #ffffff;
            --border: #e1e4e8;
        }
        
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background-color: var(--bg);
            color: var(--text);
            margin: 0;
            padding: 20px; /* Espacio externo para evitar cortes */
            font-size: 13px; /* Fuente refinada */
        }

        .main-wrapper {
            max-width: 1000px;
            margin: 20px auto; /* Centrado y margen superior extra */
            background: var(--white);
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            padding: 25px;
        }

        /* Cabecera */
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid var(--primary);
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        h1 { margin: 0; color: var(--primary); font-size: 1.5rem; font-weight: 600; }
        .btn-back { text-decoration: none; color: #666; font-weight: 500; display: flex; align-items: center; gap: 5px;}
        .btn-back:hover { color: var(--primary); }

        /* Formulario Compacto */
        .form-card {
            background: #fafbfc;
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .form-card.editing { border-left: 4px solid var(--accent); background: #fffdf5; }
        
        select, input[type="text"] {
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 13px;
            outline: none;
        }
        select { width: 180px; }
        input[type="text"] { flex-grow: 1; min-width: 200px; }
        select:focus, input:focus { border-color: var(--primary); box-shadow: 0 0 0 2px rgba(0,45,114,0.1); }

        /* Botones */
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.2s;
        }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: #001f52; }
        .btn-success { background: #28a745; color: white; }
        .btn-cancel { background: #6c757d; color: white; text-decoration: none; display: inline-block;}

        /* Tabla Refinada */
        .search-wrapper { position: relative; margin-bottom: 15px; }
        .search-box { width: 100%; padding: 8px 10px 8px 30px; border: 1px solid var(--border); border-radius: 4px; box-sizing: border-box;}
        .search-icon { position: absolute; left: 10px; top: 9px; color: #999; }

        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { text-align: left; padding: 10px; border-bottom: 2px solid var(--border); color: #555; font-weight: 600; background: #f8f9fa;}
        td { padding: 8px 10px; border-bottom: 1px solid #eee; vertical-align: middle; }
        tr:hover { background-color: #f8faff; }

        /* Acciones */
        .actions { display: flex; gap: 5px; }
        .action-btn {
            padding: 4px 8px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px; /* Iconos un poco más grandes */
            border: 1px solid transparent;
            transition: background 0.2s;
        }
        .btn-edit { color: #0056b3; background: #e7f1ff; }
        .btn-edit:hover { background: #d0e4ff; }
        
        .btn-del { color: #dc3545; background: #ffebeb; }
        .btn-del:hover { background: #ffd1d1; }

        .btn-status { font-size: 11px; padding: 2px 8px; border-radius: 10px; font-weight: bold; text-decoration: none; }
        .active { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .inactive { background: #f8d7da; color: #721c24; border: 1