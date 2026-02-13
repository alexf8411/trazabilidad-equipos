/**
 * public/js/asignacion_masiva.js
 * VERSIÓN FINAL COMBINADA
 * 
 * Incluye:
 * - Drag & drop de archivos
 * - Filtrado de lugares por sede
 * - MutationObserver para detección automática de LDAP
 * - Validación reactiva del formulario
 * - Indicadores visuales de switches
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // ========================================================================
    // REFERENCIAS A ELEMENTOS DEL DOM
    // ========================================================================
    const selectSede = document.getElementById('selectSede');
    const selectLugar = document.getElementById('selectLugar');
    const inputCorreo = document.getElementById('correo_resp_real');
    const userCard = document.getElementById('userCard');
    const btnSubmit = document.getElementById('btnSubmit');
    
    // Elementos de drag & drop (FASE 1)
    const dropzone = document.getElementById('dropzone');
    const fileInput = document.getElementById('csv_file');
    const fileInfo = document.getElementById('fileInfo');
    const fileName = document.getElementById('fileName');
    const btnUpload = document.getElementById('btnUpload');

    // ========================================================================
    // FASE 1: DRAG & DROP DE ARCHIVOS
    // ========================================================================
    if (dropzone && fileInput) {
        // Prevenir comportamiento por defecto
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, preventDefaults, false);
            document.body.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        // Efectos visuales de hover
        ['dragenter', 'dragover'].forEach(eventName => {
            dropzone.addEventListener(eventName, function() {
                dropzone.classList.add('dragover');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, function() {
                dropzone.classList.remove('dragover');
            }, false);
        });

        // Manejar drop
        dropzone.addEventListener('drop', handleDrop, false);
        fileInput.addEventListener('change', handleFiles, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            
            if (files.length > 0) {
                fileInput.files = files;
                handleFiles();
            }
        }

        function handleFiles() {
            if (fileInput.files.length > 0) {
                const file = fileInput.files[0];
                const ext = file.name.toLowerCase().split('.').pop();
                
                if (ext === 'csv' || ext === 'txt') {
                    fileName.textContent = file.name + ' (' + formatFileSize(file.size) + ')';
                    fileInfo.style.display = 'block';
                    fileInfo.style.background = '#dcfce7';
                    fileInfo.style.borderColor = '#86efac';
                    fileInfo.style.color = '#166534';
                    btnUpload.style.display = 'block';
                } else {
                    fileName.textContent = "❌ Error: El archivo debe ser .CSV o .TXT";
                    fileInfo.style.display = 'block';
                    fileInfo.style.background = '#fee2e2';
                    fileInfo.style.borderColor = '#fecaca';
                    fileInfo.style.color = '#991b1b';
                    btnUpload.style.display = 'none';
                    fileInput.value = '';
                }
            }
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }
    }

    // ========================================================================
    // FASE 2: FILTRADO DE LUGARES POR SEDE
    // ========================================================================
    if (selectSede) {
        selectSede.addEventListener('change', function() {
            const sedeSeleccionada = this.value;
            
            // Reiniciar select de lugares
            selectLugar.innerHTML = '<option value="">-- Seleccionar Ubicación --</option>';
            
            if (sedeSeleccionada === "") {
                selectLugar.disabled = true;
                validarFormulario(); // Re-validar al limpiar
                return;
            }

            // Usar variable global URTRACK_LUGARES inyectada desde PHP
            if (typeof URTRACK_LUGARES !== 'undefined') {
                const lugaresFiltrados = URTRACK_LUGARES.filter(l => l.sede === sedeSeleccionada);
                
                lugaresFiltrados.forEach(l => {
                    const option = document.createElement('option');
                    option.value = l.id;
                    option.textContent = l.nombre;
                    selectLugar.appendChild(option);
                });
                
                selectLugar.disabled = false;
            }
            
            validarFormulario();
        });
    }

    if (selectLugar) {
        selectLugar.addEventListener('change', validarFormulario);
    }

    // ========================================================================
    // FUNCIÓN CENTRALIZADA DE VALIDACIÓN
    // ========================================================================
    function validarFormulario() {
        if (!btnSubmit) return;

        const sedeValida = selectSede && selectSede.value !== "";
        const lugarValido = selectLugar && selectLugar.value !== "";
        const usuarioValido = inputCorreo && inputCorreo.value !== "";

        // Solo habilitar si las 3 condiciones se cumplen
        if (sedeValida && lugarValido && usuarioValido) {
            btnSubmit.disabled = false;
            btnSubmit.style.opacity = "1";
            btnSubmit.style.cursor = "pointer";
            // Quitar candado si existe
            btnSubmit.innerHTML = btnSubmit.innerHTML.replace("🔒 ", "");
        } else {
            btnSubmit.disabled = true;
            btnSubmit.style.opacity = "0.5";
            btnSubmit.style.cursor = "not-allowed";
        }
    }

    // ========================================================================
    // MUTATION OBSERVER (DETECTOR MÁGICO)
    // ========================================================================
    // Escucha cambios en el HTML del userCard
    // Cuando verificar_ldap.js escribe "Juan Perez", esto se dispara automáticamente
    if (userCard) {
        const observer = new MutationObserver(function(mutations) {
            // Esperar un micro-momento para asegurar que el input hidden tiene el valor
            setTimeout(validarFormulario, 100);
        });

        observer.observe(userCard, { 
            childList: true, 
            subtree: true, 
            characterData: true 
        });
    }

    // ========================================================================
    // VALIDACIÓN ANTES DE ENVIAR FORMULARIO (CORREGIDO)
    // ========================================================================
    const formConfig = document.querySelector('form[method="POST"]');
    
    if (formConfig && btnSubmit) {
        formConfig.addEventListener('submit', function(e) {
            const idLugar = selectLugar ? selectLugar.value : null;
            const noCaso = document.getElementById('no_caso');
            const correoResp = inputCorreo ? inputCorreo.value : null;

            // Validar ubicación
            if (!idLugar) {
                e.preventDefault();
                alert('⚠️ Debe seleccionar la ubicación destino');
                if (selectLugar) selectLugar.focus();
                return false;
            }

            // Validar No. de Caso
            if (noCaso && !noCaso.value.trim()) {
                e.preventDefault();
                alert('⚠️ Debe ingresar el Número de Caso');
                noCaso.focus();
                return false;
            }

            // Validar responsable principal
            if (!correoResp) {
                e.preventDefault();
                alert('⚠️ Debe verificar el Responsable Principal en LDAP');
                return false;
            }

            // Confirmación final
            const totalEquipos = btnSubmit.textContent.match(/\d+/);
            if (totalEquipos) {
                const confirmar = confirm(
                    `¿Confirmar asignación masiva de ${totalEquipos[0]} equipo(s)?\n\n` +
                    `Esta acción no se puede deshacer fácilmente.`
                );
                
                if (!confirmar) {
                    e.preventDefault();
                    return false;
                }
            }

            // TRUCO PARA QUE PHP RECIBA 'confirm_save':
            // Creamos un input oculto dinámicamente con el nombre del botón
            // antes de deshabilitar el botón real.
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'confirm_save';
            hiddenInput.value = '1';
            formConfig.appendChild(hiddenInput);

            // Ahora sí podemos deshabilitar el botón para evitar doble clic
            btnSubmit.disabled = true;
            btnSubmit.textContent = '⏳ Procesando asignación...';
            
            // NO usamos e.preventDefault() aquí, así que el formulario se envía al servidor
        });
    }

    // ========================================================================
    // INDICADOR VISUAL DE SWITCHES (ROJO POR DEFECTO)
    // ========================================================================
    const switches = document.querySelectorAll('.switch input[type="checkbox"]');
    
    switches.forEach(switchInput => {
        // Establecer color inicial (rojo por defecto OFF)
        const label = switchInput.closest('.switch-container').querySelector('.switch-label');
        if (label) {
            label.style.color = switchInput.checked ? '#166534' : '#991b1b';
        }

        // Cambiar color al hacer clic
        switchInput.addEventListener('change', function() {
            const label = this.closest('.switch-container').querySelector('.switch-label');
            
            if (label) {
                if (this.checked) {
                    label.style.color = '#166534'; // Verde
                } else {
                    label.style.color = '#991b1b'; // Rojo
                }
            }
        });
    });

    // ========================================================================
    // VALIDACIÓN INICIAL
    // ========================================================================
    validarFormulario();

    // ========================================================================
    // CONTADOR DE EQUIPOS VÁLIDOS (INFO EN CONSOLA)
    // ========================================================================
    const tablaPrev = document.querySelector('.preview-table tbody');
    
    if (tablaPrev) {
        const filasValidas = tablaPrev.querySelectorAll('.row-valid').length;
        const filasInvalidas = tablaPrev.querySelectorAll('.row-invalid').length;
        const filasDuplicadas = tablaPrev.querySelectorAll('.row-duplicated').length;

        console.log(`📊 Equipos válidos: ${filasValidas}`);
        console.log(`❌ Equipos inválidos: ${filasInvalidas}`);
        console.log(`⚠️ Equipos duplicados: ${filasDuplicadas}`);
    }
});