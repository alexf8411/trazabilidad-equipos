/**
 * public/js/asignacion_masiva.js
 * Lógica de filtrado de lugares y validación reactiva del formulario
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // Referencias a elementos del DOM
    const selectSede = document.getElementById('selectSede');
    const selectLugar = document.getElementById('selectLugar');
    const inputCorreo = document.getElementById('correo_resp_real');
    const userCard = document.getElementById('userCard');
    const btnSubmit = document.getElementById('btnSubmit');

    // 1. Lógica de Filtrado de Lugares
    if (selectSede) {
        selectSede.addEventListener('change', function() {
            const sedeSeleccionada = this.value;
            
            // Reiniciar select de lugares
            selectLugar.innerHTML = '<option value="">-- Seleccionar --</option>';
            
            if (sedeSeleccionada === "") {
                selectLugar.disabled = true;
                validarFormulario(); // Re-validar al limpiar
                return;
            }

            // Usamos la variable global URTRACK_LUGARES inyectada desde PHP
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

    // 2. Función Centralizada de Validación
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
            btnSubmit.innerHTML = btnSubmit.innerHTML.replace("🔒 ", ""); // Quitar candado si existe
        } else {
            btnSubmit.disabled = true;
            btnSubmit.style.opacity = "0.5";
            btnSubmit.style.cursor = "not-allowed";
        }
    }

    // 3. DETECTOR MÁGICO (MutationObserver)
    // Esto soluciona el problema: Escucha cambios en el HTML del userCard
    // Cuando verificar_ldap.js escribe "Juan Perez", esto se dispara.
    if (userCard) {
        const observer = new MutationObserver(function(mutations) {
            // Esperar un micro-momento para asegurar que el input hidden ya tiene el valor
            setTimeout(validarFormulario, 100);
        });

        observer.observe(userCard, { childList: true, subtree: true, characterData: true });
    }

    // Validación inicial por si acaso
    validarFormulario();
});