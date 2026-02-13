/**
 * public/js/registro_movimiento.js
 * Script para filtrado de lugares por sede
 * Los scripts de LDAP se mantienen en archivos separados
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // ========================================================================
    // FILTRADO DE LUGARES POR SEDE
    // ========================================================================
    window.filtrarLugares = function() {
        const sede = document.getElementById('selectSede').value;
        const selectLugar = document.getElementById('selectLugar');
        
        selectLugar.innerHTML = '<option value="">-- Seleccionar Ubicación --</option>';
        
        if (sede) {
            lugaresData
                .filter(l => l.sede === sede)
                .forEach(l => {
                    selectLugar.innerHTML += `<option value="${l.id}">${l.nombre}</option>`;
                });
            
            selectLugar.disabled = false;
        } else {
            selectLugar.disabled = true;
        }
    };

    // ========================================================================
    // MOSTRAR/OCULTAR USER CARDS CUANDO SE VERIFICAN USUARIOS
    // ========================================================================
    
    // Estas funciones son llamadas desde verificar_ldap.js
    // Solo agregamos helpers para mejorar la UX
    
    const btnSubmit = document.getElementById('btnSubmit');
    
    // Habilitar botón submit cuando se verifique el usuario principal
    window.habilitarSubmit = function() {
        if (btnSubmit) {
            btnSubmit.disabled = false;
            btnSubmit.classList.remove('btn-submit-disabled');
            btnSubmit.classList.add('btn-submit-enabled');
        }
    };
    
    window.deshabilitarSubmit = function() {
        if (btnSubmit) {
            btnSubmit.disabled = true;
            btnSubmit.classList.add('btn-submit-disabled');
            btnSubmit.classList.remove('btn-submit-enabled');
        }
    };

    // ========================================================================
    // VALIDACIÓN ADICIONAL DEL FORMULARIO
    // ========================================================================
    
    const form = document.querySelector('form[method="POST"]');
    
    if (form) {
        form.addEventListener('submit', function(e) {
            const hostname = document.querySelector('input[name="hostname"]').value.trim();
            const noCaso = document.querySelector('input[name="no_caso"]').value.trim();
            const idLugar = document.querySelector('select[name="id_lugar"]').value;
            
            // Validar hostname
            if (!hostname) {
                e.preventDefault();
                alert('⚠️ Debe ingresar el hostname del equipo');
                return false;
            }
            
            // Validar No. de Caso
            if (!noCaso) {
                e.preventDefault();
                alert('⚠️ Debe ingresar el Número de Caso');
                return false;
            }
            
            // Validar ubicación destino
            if (!idLugar) {
                e.preventDefault();
                alert('⚠️ Debe seleccionar la ubicación destino');
                return false;
            }
            
            // Validar que se haya verificado usuario principal
            const correoRespReal = document.getElementById('correo_resp_real').value;
            if (!correoRespReal) {
                e.preventDefault();
                alert('⚠️ Debe verificar el Responsable Principal en LDAP');
                return false;
            }
        });
    }

    // ========================================================================
    // AUTO-MAYÚSCULAS EN HOSTNAME
    // ========================================================================
    
    const hostnameInput = document.querySelector('input[name="hostname"]');
    
    if (hostnameInput) {
        hostnameInput.addEventListener('input', function() {
            const cursorPos = this.selectionStart;
            this.value = this.value.toUpperCase();
            this.setSelectionRange(cursorPos, cursorPos);
        });
    }

    // ========================================================================
    // INDICADOR VISUAL DE SWITCHES ACTIVADOS
    // ========================================================================
    
    const switches = document.querySelectorAll('.switch input[type="checkbox"]');
    
    switches.forEach(switchInput => {
        switchInput.addEventListener('change', function() {
            const label = this.closest('.switch-container').querySelector('.switch-label');
            
            if (this.checked) {
                label.style.color = '#166534';
                label.style.fontWeight = 'bold';
            } else {
                label.style.color = '#991b1b';
                label.style.fontWeight = 'bold';
            }
        });
    });
});