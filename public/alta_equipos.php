<?php
/**
 * URTRACK - Alta de Equipos
 * Versión 3.0 OPTIMIZADA
 * 
 * OPTIMIZACIONES IMPLEMENTADAS:
 * ✅ Query de bodega con caché en sesión (evita LIKE peligroso)
 * ✅ Query exacta sin full table scan
 * ✅ Validaciones robustas
 * ✅ CSS y JS en archivos externos
 * ✅ Mismo diseño visual original
 * ✅ Misma lógica de base de datos
 */

require_once '../core/db.php';
require_once '../core/session.php';

// ============================================================================
// CONTROL DE ACCESO
// ============================================================================
$roles_permitidos = ['Administrador', 'Recursos'];
if (!isset($_SESSION['rol']) || !in_array($_SESSION['rol'], $roles_permitidos)) {
    header('Location: dashboard.php');
    exit;
}

$msg = "";

// ============================================================================
// FUNCIÓN OPTIMIZADA: Obtener bodega CON CACHÉ
// ============================================================================
/**
 * Obtiene la Bodega de Tecnología con caché en sesión
 * 
 * ANTES: SELECT ... WHERE nombre LIKE '%Bodega%' (PELIGROSO - Full table scan)
 * AHORA: SELECT ... WHERE nombre = ? (SEGURO - Usa índice) + Caché
 * 
 * @param PDO $pdo
 * @return array Datos de la bodega
 * @throws Exception Si no existe la bodega
 */
function obtenerBodega($pdo) {
    // Cachear en sesión para evitar query repetida
    if (!isset($_SESSION['bodega_cache'])) {
        // Query EXACTA sin LIKE peligroso
        $stmt = $pdo->prepare("SELECT id, sede, nombre FROM lugares WHERE nombre = ? LIMIT 1");
        $stmt->execute(['Bodega de Tecnología']);
        $bodega = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Validar que existe
        if (!$bodega) {
            throw new Exception("ERROR CRÍTICO: No existe la ubicación 'Bodega de Tecnología' en la base de datos");
        }
        
        $_SESSION['bodega_cache'] = $bodega;
    }
    
    return $_SESSION['bodega_cache'];
}

// ============================================================================
// PROCESAMIENTO DEL FORMULARIO
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Sanitización de inputs
    $serial = trim($_POST['serial']);
    $placa = trim($_POST['placa']); 
    $marca = trim($_POST['marca']);
    $modelo = trim($_POST['modelo']);
    $vida_util = (int)$_POST['vida_util'];
    $precio = (float)$_POST['precio'];
    $modalidad = $_POST['modalidad'];
    $fecha_compra = $_POST['fecha_compra'];
    $orden_compra = trim($_POST['orden_compra']);

    // Datos del usuario autenticado
    $usuario_autenticado = $_SESSION['usuario_id'] ?? $_SESSION['nombre'];
    $tecnico_nombre = $_SESSION['nombre'];
    
    // Descripción con formato "OC: valor"
    $desc_evento_final = "OC: " . $orden_compra;

    // Validaciones adicionales
    if (empty($serial) || empty($placa) || empty($orden_compra)) {
        $msg = "<div class='toast error'>⚠️ Error: Serial, Placa y Orden de Compra son obligatorios</div>";
    } elseif ($vida_util < 1 || $vida_util > 50) {
        $msg = "<div class='toast error'>⚠️ Error: La vida útil debe estar entre 1 y 50 años</div>";
    } elseif ($precio <= 0) {
        $msg = "<div class='toast error'>⚠️ Error: El precio debe ser mayor a cero</div>";
    } else {
        try {
            // Obtener bodega (con caché)
            $bodega = obtenerBodega($pdo);
            
            // Iniciar transacción
            $pdo->beginTransaction();

            // A. INSERTAR EN EQUIPOS (Registro Maestro)
            $sql_equipo = "INSERT INTO equipos (
                                placa_ur, serial, marca, modelo, 
                                vida_util, precio, 
                                fecha_compra, modalidad, estado_maestro
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Alta')";
            
            $stmt = $pdo->prepare($sql_equipo);
            $stmt->execute([
                $placa, $serial, $marca, $modelo, 
                $vida_util, $precio, 
                $fecha_compra, $modalidad
            ]);

            // B. INSERTAR EN BITÁCORA (Evento de Alta)
            $sql_bitacora = "INSERT INTO bitacora (
                                serial_equipo, id_lugar, sede, ubicacion, 
                                tipo_evento, correo_responsable, fecha_evento, 
                                tecnico_responsable, hostname, desc_evento, check_sccm
                              ) VALUES (?, ?, ?, ?, 'Alta', ?, NOW(), ?, ?, ?, 0)";
            
            $stmt_b = $pdo->prepare($sql_bitacora);
            $stmt_b->execute([
                $serial, 
                $bodega['id'], 
                $bodega['sede'], 
                $bodega['nombre'],
                $usuario_autenticado,  // Responsable es el usuario autenticado
                $tecnico_nombre,
                $serial,               // Hostname inicial = Serial
                $desc_evento_final     // "OC: 2026-9988"
            ]);

            // Confirmar transacción
            $pdo->commit();
            
            // Redireccionar con mensaje de éxito
            header("Location: alta_equipos.php?status=success&p=" . urlencode($placa));
            exit;

        } catch (PDOException $e) {
            // Revertir transacción en caso de error
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            
            // Manejo de errores específicos
            if ($e->getCode() == '23000') {
                // Error de duplicado (UNIQUE constraint)
                $msg = "<div class='toast error'>⚠️ Error: El <b>Serial</b> o la <b>Placa UR</b> ya están registrados en el sistema.</div>";
            } else {
                // Otros errores SQL
                error_log("Error en alta_equipos.php: " . $e->getMessage());
                $msg = "<div class='toast error'>❌ Error SQL: " . $e->getMessage() . "</div>";
            }
            
        } catch (Exception $e) {
            // Errores generales
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $msg = "<div class='toast error'>❌ " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}

// ============================================================================
// MENSAJE DE ÉXITO
// ============================================================================
if (isset($_GET['status']) && $_GET['status'] == 'success') {
    $placa_creada = htmlspecialchars($_GET['p'] ?? '');
    $msg = "<div class='toast success'>✅ Equipo <b>$placa_creada</b> ingresado correctamente a tu cargo.</div>";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alta de Equipos - URTRACK</title>
    
    <!-- CSS EXTERNO -->
    <link rel="stylesheet" href="../css/alta_equipos.css">
</head>
<body>

<div class="container">
    <!-- Banner de importación masiva -->
    <div class="bulk-banner">
        <div>
            <strong>¿Tienes muchos equipos?</strong>
            <p style="margin: 5px 0 0 0; font-size: 0.85rem; color: #555;">Sube un archivo CSV con las Placas y Seriales.</p>
        </div>
        <a href="importar_csv.php" class="btn-bulk">📥 Importación Masiva</a>
    </div>

    <!-- Card principal -->
    <div class="main-card">
        <header>
            <h1>➕ Registro Individual</h1>
            <a href="dashboard.php" style="text-decoration:none; color:#666;">⬅ Volver</a>
        </header>

        <!-- Mensajes del sistema -->
        <?= $msg ?>

        <!-- Formulario de alta -->
        <form method="POST" id="formAlta">
            <div class="form-grid">
                <!-- Serial Fabricante -->
                <div class="form-group">
                    <label for="serial">Serial Fabricante *</label>
                    <input type="text" 
                           id="serial"
                           name="serial" 
                           required 
                           placeholder="Ej: 5CD2340JL" 
                           autofocus>
                </div>
                
                <!-- Placa Inventario UR -->
                <div class="form-group">
                    <label for="placa">Placa Inventario UR *</label>
                    <input type="text" 
                           id="placa"
                           name="placa" 
                           required 
                           placeholder="Ej: 004589">
                </div>

                <!-- Marca -->
                <div class="form-group">
                    <label for="marca">Marca *</label>
                    <select id="marca" name="marca" required>
                        <option value="">-- Seleccionar --</option>
                        <option value="HP">HP</option>
                        <option value="Lenovo">Lenovo</option>
                        <option value="Dell">Dell</option>
                        <option value="Apple">Apple</option>
                        <option value="Asus">Asus</option>
                        <option value="Microsoft">Microsoft</option>
                        <option value="Acer">Acer</option>
                        <option value="Samsung">Samsung</option>
                        <option value="Otro">Otro</option>
                    </select>
                </div>
                
                <!-- Modelo -->
                <div class="form-group">
                    <label for="modelo">Modelo *</label>
                    <input type="text" 
                           id="modelo"
                           name="modelo" 
                           required 
                           placeholder="Ej: ProBook 440">
                </div>

                <!-- Orden de Compra -->
                <div class="form-group">
                    <label for="orden_compra">Orden de Compra *</label>
                    <input type="text" 
                           id="orden_compra"
                           name="orden_compra" 
                           required 
                           placeholder="Ej: 2026-9988-OC">
                    <small style="color:#666; font-size:0.8rem;">Se guardará con prefijo OC:</small>
                </div>

                <!-- Fecha de Compra -->
                <div class="form-group">
                    <label for="fecha_compra">Fecha de Compra *</label>
                    <input type="date" 
                           id="fecha_compra"
                           name="fecha_compra" 
                           required 
                           value="<?= date('Y-m-d') ?>">
                </div>

                <!-- Vida Útil -->
                <div class="form-group">
                    <label for="vida_util">Vida Útil (Años) *</label>
                    <input type="number" 
                           id="vida_util"
                           name="vida_util" 
                           min="1" 
                           max="50" 
                           required 
                           placeholder="Ej: 5" 
                           value="5">
                </div>
                
                <!-- Precio -->
                <div class="form-group">
                    <label for="precio">Precio (COP) *</label>
                    <input type="number" 
                           id="precio"
                           name="precio" 
                           min="0" 
                           step="0.01" 
                           required 
                           placeholder="Ej: 4500000">
                </div>
                
                <!-- Modalidad -->
                <div class="form-group full-width">
                    <label for="modalidad">Modalidad *</label>
                    <select id="modalidad" name="modalidad" required>
                        <option value="Propio">Propio</option>
                        <option value="Leasing">Leasing</option>
                        <option value="Proyecto">Proyecto</option>
                    </select>
                </div>

                <!-- Info Box -->
                <div class="full-width info-box">
                    ℹ️ <strong>Nota:</strong> El equipo ingresará a <strong>Bodega de Tecnología</strong> bajo tu responsabilidad (<?= htmlspecialchars($usuario_autenticado) ?>).
                </div>
                
                <!-- Botón Submit -->
                <div class="full-width">
                    <button type="submit" class="btn-submit">💾 Guardar Equipo</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- JAVASCRIPT EXTERNO -->
<script src="../public/js/alta_equipos.js"></script>

</body>
</html>