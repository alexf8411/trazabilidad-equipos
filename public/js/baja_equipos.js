/**
 * public/js/baja_equipos.js
 * Script para el formulario de Baja Masiva de Equipos
 */

document.addEventListener('DOMContentLoaded', function() {
    
    const form = document.getElementById('formBaja');
    const textareaSeriales = document.querySelector('textarea[name="seriales_raw"]');
    const btnSubmit = document.querySelector('.btn-danger-submit');

    // ========================================================================
    // VALIDACIÓN Y CONFIRMACIÓN DE ENVÍO
    // ========================================================================
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const seriales = textareaSeriales.value.trim();
            const motivoBaja = document.getElementById('motivo_baja').value;
            const justificacion = document.getElementById('justificacion').value.trim();

            // Validar que haya seriales
            if (!seriales) {
                alert('⚠️ Debe ingresar al menos un serial');
                textareaSeriales.focus();
                return false;
            }

            // Validar motivo de baja
            if (!motivoBaja) {
                alert('⚠️ Debe seleccionar el motivo de la baja');
                document.getElementById('motivo_baja').focus();
                return false;
            }

            // Validar justificación
            if (!justificacion) {
                alert('⚠️ Debe ingresar una justificación técnica');
                document.getElementById('justificacion').focus();
                return false;
            }

            // Contar seriales
            const listaSeriales = seriales.split(/\r\n|\r|\n/).filter(s => s.trim() !== '');
            const cantidadSeriales = listaSeriales.length;

            // Confirmación con advertencia
            const mensaje = `⚠️ CONFIRMACIÓN DE BAJA MASIVA

📦 Cantidad de equipos: ${cantidadSeriales}
🔴 Motivo: ${motivoBaja}
📝 Justificación: ${justificacion}

Esta acción:
• Marcará ${cantidadSeriales} equipo(s) como BAJA en el sistema
• Guardará en bitácora: "${motivoBaja} | ${justificacion}"
• Generará un Acta de Baja automática
• Es IRREVERSIBLE (solo Administrador puede revertir)

¿CONFIRMA LA BAJA DEFINITIVA?`;

            if (confirm(mensaje)) {
                // Deshabilitar botón para evitar doble envío
                btnSubmit.disabled = true;
                btnSubmit.textContent = '⏳ Procesando bajas...';
                
                // Enviar formulario
                form.submit();
            }
        });
    }

    // ========================================================================
    // AUTO-MAYÚSCULAS EN SERIALES
    // ========================================================================
    if (textareaSeriales) {
        textareaSeriales.addEventListener('input', function() {
            const cursorStart = this.selectionStart;
            const cursorEnd = this.selectionEnd;
            
            this.value = this.value.toUpperCase();
            
            this.setSelectionRange(cursorStart, cursorEnd);
        });

        // Mostrar contador de seriales en tiempo real
        textareaSeriales.addEventListener('input', actualizarContador);
        actualizarContador(); // Ejecutar al cargar
    }

    function actualizarContador() {
        const texto = textareaSeriales.value.trim();
        
        if (texto) {
            const seriales = texto.split(/\r\n|\r|\n/).filter(s => s.trim() !== '');
            const cantidad = seriales.length;
            
            // Buscar o crear contador
            let contador = document.getElementById('contador-seriales');
            
            if (!contador) {
                contador = document.createElement('small');
                contador.id = 'contador-seriales';
                contador.className = 'hint';
                contador.style.fontWeight = 'bold';
                contador.style.color = '#dc3545';
                textareaSeriales.parentNode.appendChild(contador);
            }
            
            contador.textContent = `📊 Total de seriales: ${cantidad}`;
        } else {
            const contador = document.getElementById('contador-seriales');
            if (contador) contador.remove();
        }
    }

    // ========================================================================
    // VALIDACIÓN DE FORMATO DE SERIALES
    // ========================================================================
    if (textareaSeriales) {
        textareaSeriales.addEventListener('blur', function() {
            const seriales = this.value.trim().split(/\r\n|\r|\n/).filter(s => s.trim() !== '');
            
            // Verificar que no haya seriales vacíos intercalados
            let serialesInvalidos = [];
            
            seriales.forEach((serial, index) => {
                // Serial muy corto (menos de 4 caracteres es sospechoso)
                if (serial.length < 4) {
                    serialesInvalidos.push(`Línea ${index + 1}: "${serial}" es muy corto`);
                }
                
                // Serial con caracteres extraños (opcional - ajustar según necesidad)
                if (!/^[A-Z0-9\-_]+$/i.test(serial)) {
                    serialesInvalidos.push(`Línea ${index + 1}: "${serial}" tiene caracteres inválidos`);
                }
            });

            if (serialesInvalidos.length > 0 && serialesInvalidos.length <= 5) {
                const mensaje = '⚠️ Advertencia: Posibles seriales inválidos:\n\n' + 
                                serialesInvalidos.join('\n') + 
                                '\n\n¿Desea continuar de todos modos?';
                
                if (!confirm(mensaje)) {
                    this.focus();
                }
            }
        });
    }

    // ========================================================================
    // PREVENIR SALIDA ACCIDENTAL SI HAY DATOS
    // ========================================================================
    let formularioModificado = false;

    if (textareaSeriales) {
        textareaSeriales.addEventListener('input', function() {
            formularioModificado = this.value.trim().length > 0;
        });
    }

    const motivoBajaSelect = document.getElementById('motivo_baja');
    const justificacionInput = document.getElementById('justificacion');

    if (motivoBajaSelect) {
        motivoBajaSelect.addEventListener('change', function() {
            formularioModificado = this.value.length > 0;
        });
    }

    if (justificacionInput) {
        justificacionInput.addEventListener('input', function() {
            formularioModificado = this.value.trim().length > 0;
        });
    }

    window.addEventListener('beforeunload', function(e) {
        if (formularioModificado) {
            e.preventDefault();
            e.returnValue = '¿Seguro que desea salir? Los datos no guardados se perderán.';
            return e.returnValue;
        }
    });

    // Limpiar flag al enviar
    if (form) {
        form.addEventListener('submit', function() {
            formularioModificado = false;
        });
    }

    // ========================================================================
    // FUNCIONALIDAD EXTRA: LIMPIAR ESPACIOS Y DUPLICADOS
    // ========================================================================
    
    // Botón para limpiar duplicados (opcional - agregar al HTML si se quiere)
    const btnLimpiar = document.getElementById('btn-limpiar-duplicados');
    
    if (btnLimpiar) {
        btnLimpiar.addEventListener('click', function() {
            const seriales = textareaSeriales.value
                .trim()
                .split(/\r\n|\r|\n/)
                .map(s => s.trim().toUpperCase())
                .filter(s => s !== '');
            
            // Eliminar duplicados
            const serialesUnicos = [...new Set(seriales)];
            
            const duplicados = seriales.length - serialesUnicos.length;
            
            textareaSeriales.value = serialesUnicos.join('\n');
            
            if (duplicados > 0) {
                alert(`✅ Se eliminaron ${duplicados} serial(es) duplicado(s)`);
            } else {
                alert('✅ No se encontraron duplicados');
            }
            
            actualizarContador();
        });
    }
});