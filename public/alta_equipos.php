<?php
/**
 * URTRACK - Alta de Equipos
 * Versión 3.0 OPTIMIZADA
 * 
 * OPTIMIZACIONES:
 * ✅ Caché de bodega en sesión (evita query repetida)
 * ✅ Query exacta sin LIKE peligroso
 * ✅ Validaciones robustas
 * ✅ Código limpio y modular
 */

require_once '../core/db.php';
require_once '../core/session.php';

// Verificar permisos
if (!in_array($_SESSION['rol'], ['Administrador', 'Recursos'])) {
    header('Location: dashboard.php');
    exit;
}

$mensaje = '';

// ============================================================================
// OBTENER BODEGA CON CACHÉ (OPTIMIZACIÓN CRÍTICA)
// ============================================================================
function obtenerBodega($pdo) {
    // Cachear bodega en sesión para evitar query en cada request
    if (!isset($_SESSION['bodega_cache'])) {
        $stmt = $pdo->prepare("SELECT id, sede, nombre FROM lugares WHERE nombre = ? LIMIT 1");
        $stmt->execute(['Bodega de Tecnología']);
        $bodega = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$bodega) {
            throw new Exception("ERROR: No existe la ubicación 'Bodega de Tecnología' en la tabla lugares");
        }
        
        $_SESSION['bodega_cache'] = $bodega;
    }
    
    return $_SESSION['bodega_cache'];
}

// ============================================================================
// PROCESAMIENTO DEL FORMULARIO
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitización
    $serial = strtoupper(trim($_POST['serial']));
    $placa = strtoupper(trim($_POST['placa']));
    $marca = trim($_POST['marca']);
    $modelo = trim($_POST['modelo']);
    $vida_util = (int)$_POST['vida_util'];
    $precio = (float)$_POST['precio'];
    $modalidad = $_POST['modalidad'];
    $fecha_compra = $_POST['fecha_compra'];
    $orden_compra = trim($_POST['orden_compra']);

    // Validaciones
    if (empty($serial) || empty($placa) || empty($orden_compra)) {
        $mensaje = '<div class="alert alert-error">⚠️ Los campos Serial, Placa y Orden de Compra son obligatorios</div>';
    } elseif ($vida_util < 1 || $vida_util > 50) {
        $mensaje = '<div class="alert alert-error">⚠️ La vida útil debe estar entre 1 y 50 años</div>';
    } elseif ($precio <= 0) {
        $mensaje = '<div class="alert alert-error">⚠️ El precio debe ser mayor a cero</div>';
    } else {
        try {
            $bodega = obtenerBodega($pdo);
            
            $pdo->beginTransaction();

            // Insertar equipo
            $stmt = $pdo->prepare("
                INSERT INTO equipos (placa_ur, serial, marca, modelo, vida_util, precio, fecha_compra, modalidad, estado_maestro)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Alta')
            ");
            $stmt->execute([$placa, $serial, $marca, $modelo, $vida_util, $precio, $fecha_compra, $modalidad]);

            // Insertar en bitácora
            $stmt_bit = $pdo->prepare("
                INSERT INTO bitacora (
                    serial_equipo, id_lugar, sede, ubicacion, tipo_evento,
                    correo_responsable, fecha_evento, tecnico_responsable, hostname, desc_evento
                ) VALUES (?, ?, ?, ?, 'Alta', ?, NOW(), ?, ?, ?)
            ");
            $stmt_bit->execute([
                $serial,
                $bodega['id'],
                $bodega['sede'],
                $bodega['nombre'],
                $_SESSION['usuario_id'] ?? $_SESSION['nombre'],
                $_SESSION['nombre'],
                $serial, // Hostname inicial = Serial
                'OC: ' . $orden_compra
            ]);

            $pdo->commit();
            
            header("Location: alta_equipos.php?status=success&p=" . urlencode($placa));
            exit;

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            
            if ($e->getCode() == '23000') {
                $mensaje = '<div class="alert alert-error">⚠️ El Serial o Placa ya están registrados en el sistema</div>';
            } else {
                error_log("Error alta_equipos.php: " . $e->getMessage());
                $mensaje = '<div class="alert alert-error">❌ Error al registrar el equipo. Contacte al administrador</div>';
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $mensaje = '<div class="alert alert-error">❌ ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

// Mensaje de éxito
if (isset($_GET['status']) && $_GET['status'] == 'success') {
    $placa_creada = htmlspecialchars($_GET['p'] ?? '');
    $mensaje = '<div class="alert alert-success">✅ Equipo <strong>' . $placa_creada . '</strong> registrado correctamente en Bodega de Tecnología</div>';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alta de Equipos - URTRACK</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        /* Estilos específicos para alta de equipos */
        .bulk-banner {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .bulk-banner-text h3 {
            margin: 0 0 5px 0;
            font-size: 1.1rem;
        }

        .bulk-banner-text p {
            margin: 0;
            opacity: 0.9;
            font-size: 0.9rem;
        }

        .btn-bulk {
            background: white;
            color: #667eea;
            padding: 12px 24px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 700;
            transition: transform 0.2s;
            white-space: nowrap;
        }

        .btn-bulk:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .info-highlight {
            background: var(--bg-secondary);
            border-left: 4px solid var(--primary-color);
            padding: 15px;
            border-radius: 6px;
            font-size: 0.9rem;
            margin-top: 10px;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .bulk-banner {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }

            .btn-bulk {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Banner de importación masiva -->
    <div class="bulk-banner">
        <div class="bulk-banner-text">
            <h3>📥 ¿Tienes muchos equipos?</h3>
            <p>Sube un archivo CSV con las placas y seriales para registro masivo</p>
        </div>
        <a href="importar_csv.php" class="btn-bulk">Importación Masiva</a>
    </div>

    <div class="card fade-in">
        <div class="card-header">
            <h1>➕ Registro Maestro de Equipos</h1>
            <p>Alta individual de activos tecnológicos</p>
        </div>

        <div class="card-body">
            <?php if ($mensaje) echo $mensaje; ?>

            <form method="POST" data-validate>
                <div class="form-grid">
                    <!-- Fila 1: Serial y Placa -->
                    <div class="form-group">
                        <label for="serial">Serial Fabricante *</label>
                        <input type="text" 
                               id="serial" 
                               name="serial" 
                               required 
                               placeholder="Ej: 5CD2340JL"
                               data-uppercase
                               autofocus>
                    </div>

                    <div class="form-group">
                        <label for="placa">Placa Inventario UR *</label>
                        <input type="text" 
                               id="placa" 
                               name="placa" 
                               required 
                               placeholder="Ej: 004589"
                               data-uppercase>
                    </div>

                    <!-- Fila 2: Marca y Modelo -->
                    <div class="form-group">
                        <label for="marca">Marca *</label>
                        <select id="marca" name="marca" required>
                            <option value="">-- Seleccionar Marca --</option>
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

                    <div class="form-group">
                        <label for="modelo">Modelo *</label>
                        <input type="text" 
                               id="modelo" 
                               name="modelo" 
                               required 
                               placeholder="Ej: ProBook 440 G8">
                    </div>

                    <!-- Fila 3: Orden de Compra y Fecha -->
                    <div class="form-group">
                        <label for="orden_compra">Orden de Compra *</label>
                        <input type="text" 
                               id="orden_compra" 
                               name="orden_compra" 
                               required 
                               placeholder="Ej: 2026-9988-OC">
                        <small class="text-muted">Se guardará con prefijo "OC:" en bitácora</small>
                    </div>

                    <div class="form-group">
                        <label for="fecha_compra">Fecha de Compra *</label>
                        <input type="date" 
                               id="fecha_compra" 
                               name="fecha_compra" 
                               required 
                               value="<?= date('Y-m-d') ?>">
                    </div>

                    <!-- Fila 4: Vida Útil y Precio -->
                    <div class="form-group">
                        <label for="vida_util">Vida Útil (Años) *</label>
                        <input type="number" 
                               id="vida_util" 
                               name="vida_util" 
                               min="1" 
                               max="50" 
                               required 
                               value="5"
                               placeholder="Entre 1 y 50 años">
                    </div>

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

                    <!-- Fila 5: Modalidad (ancho completo) -->
                    <div class="form-group full-width">
                        <label for="modalidad">Modalidad de Adquisición *</label>
                        <select id="modalidad" name="modalidad" required>
                            <option value="Propio">Propio</option>
                            <option value="Leasing">Leasing</option>
                            <option value="Proyecto">Proyecto</option>
                        </select>
                    </div>

                    <!-- Información importante -->
                    <div class="form-group full-width">
                        <div class="info-highlight">
                            <strong>ℹ️ Trazabilidad Automática:</strong> El equipo será registrado inmediatamente en 
                            <strong>Bodega de Tecnología</strong> bajo tu custodia 
                            (<?= htmlspecialchars($_SESSION['usuario_id'] ?? $_SESSION['nombre']) ?>). 
                            El hostname inicial será el número de serial.
                        </div>
                    </div>

                    <!-- Botón de envío -->
                    <div class="form-group full-width">
                        <button type="submit" class="btn btn-primary btn-block">
                            💾 Registrar e Ingresar a Bodega
                        </button>
                    </div>
                </div>
            </form>

            <div class="text-center mt-3">
                <a href="dashboard.php" class="btn btn-outline">⬅ Volver al Dashboard</a>
            </div>
        </div>
    </div>
</div>

<script src="../public/js/app.js"></script>
<script>
// Funcionalidad específica para alta de equipos
document.addEventListener('DOMContentLoaded', function() {
    // Convertir a mayúsculas automáticamente
    const upperInputs = document.querySelectorAll('[data-uppercase]');
    upperInputs.forEach(input => {
        input.addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
    });

    // Formatear precio con separadores de miles al escribir
    const precioInput = document.getElementById('precio');
    if (precioInput) {
        precioInput.addEventListener('blur', function() {
            if (this.value) {
                const valor = parseFloat(this.value);
                if (!isNaN(valor)) {
                    // Mostrar hint de formato
                    const formatted = new Intl.NumberFormat('es-CO').format(valor);
                    const hint = document.createElement('small');
                    hint.className = 'text-muted';
                    hint.textContent = 'Valor: $' + formatted + ' COP';
                    
                    const existing = this.parentNode.querySelector('.price-hint');
                    if (existing) existing.remove();
                    
                    hint.className += ' price-hint';
                    this.parentNode.appendChild(hint);
                }
            }
        });
    }
});
</script>
</body>
</html>
