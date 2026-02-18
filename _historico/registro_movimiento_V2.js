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
    // AUTO-SELECCIÓN DE BODEGA EN DEVOLUCIONES
    // ========================================================================
    const tipoEventoSelect = document.getElementById('tipo_evento');
    const selectSede = document.getElementById('selectSede');
    const selectLugar = document.getElementById('selectLugar');
    
    if (tipoEventoSelect) {
        tipoEventoSelect.addEventListener('change', function() {
            if (this.value === 'Devolución') {
                // Pre-seleccionar "Sede" (buscar la sede que contenga "Bogotá" o la principal)
                const sedeDefault = 'Sede'; // Ajusta según tu catálogo
                
                // Buscar si existe "Bodega de Tecnología" en los datos
                const bodegaTecno = lugaresData.find(l => 
                    l.nombre.toLowerCase().includes('bodega') && 
                    l.nombre.toLowerCase().includes('tecnolog')
                );
                
                if (bodegaTecno) {
                    // Seleccionar la sede de la bodega
                    selectSede.value = bodegaTecno.sede;
                    
                    // Disparar el filtrado de lugares
                    filtrarLugares();
                    
                    // Esperar un momento para que se llene el select de lugares
                    setTimeout(() => {
                        selectLugar.value = bodegaTecno.id;
                    }, 100);
                    
                    // Resaltar visualmente
                    selectSede.style.borderColor = '#f59e0b';
                    selectLugar.style.borderColor = '#f59e0b';
                    
                    setTimeout(() => {
                        selectSede.style.borderColor = '';
                        selectLugar.style.borderColor = '';
                    }, 2000);
                }
            } else {
                // Si cambia a Asignación, limpiar las selecciones
                selectSede.value = '';
                selectLugar.innerHTML = '<option value="">-- Primero elija Sede --</option>';
                selectLugar.disabled = true;
            }
        });
    }

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
    // VALIDACIÓN ADICIONAL DEL FORMULARIO CON ADVERTENCIA DE COMPLIANCE
    // ========================================================================
    
    const form = document.querySelector('form[method="POST"]');
    
    if (form) {
        form.addEventListener('submit', function(e) {
            const hostname = document.querySelector('input[name="hostname"]').value.trim();
            const noCaso = document.querySelector('input[name="no_caso"]').value.trim();
            const idLugar = document.querySelector('select[name="id_lugar"]').value;
            const tipoEvento = document.querySelector('select[name="tipo_evento"]').value;
            
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
            
            // ============================================================
            // VALIDACIÓN DE COMPLIANCE PARA ASIGNACIONES
            // ============================================================
            if (tipoEvento === 'Asignación') {
                const checkAntivirus = document.querySelector('input[name="check_antivirus"]');
                const checkSCCM = document.querySelector('input[name="check_sccm"]');
                
                const antivirusOK = checkAntivirus && checkAntivirus.checked;
                const sccmOK = checkSCCM && checkSCCM.checked;
                
                // Si alguno de los dos está desactivado, mostrar advertencia
                if (!antivirusOK || !sccmOK) {
                    e.preventDefault();
                    
                    let mensaje = '⚠️ ADVERTENCIA DE COMPLIANCE\n\n';
                    mensaje += 'Los siguientes agentes NO están marcados como instalados:\n\n';
                    
                    if (!antivirusOK) {
                        mensaje += '❌ Antivirus Corporativo\n';
                    }
                    if (!sccmOK) {
                        mensaje += '❌ Agente SCCM\n';
                    }
                    
                    mensaje += '\n🔒 Estos agentes son OBLIGATORIOS en todos los equipos asignados según políticas de seguridad institucional.\n\n';
                    mensaje += '¿Desea continuar bajo su propio criterio y responsabilidad?\n\n';
                    mensaje += '• Presione ACEPTAR para continuar sin los agentes (no recomendado)\n';
                    mensaje += '• Presione CANCELAR para volver y marcar los agentes';
                    
                    const continuar = confirm(mensaje);
                    
                    if (!continuar) {
                        // Resaltar visualmente los switches que están apagados
                        if (!antivirusOK && checkAntivirus) {
                            const parentDiv = checkAntivirus.closest('.switch-container');
                            parentDiv.style.backgroundColor = '#fef2f2';
                            parentDiv.style.border = '2px solid #dc2626';
                            parentDiv.style.padding = '10px';
                            parentDiv.style.borderRadius = '8px';
                        }
                        
                        if (!sccmOK && checkSCCM) {
                            const parentDiv = checkSCCM.closest('.switch-container');
                            parentDiv.style.backgroundColor = '#fef2f2';
                            parentDiv.style.border = '2px solid #dc2626';
                            parentDiv.style.padding = '10px';
                            parentDiv.style.borderRadius = '8px';
                        }
                        
                        // Scroll hacia la sección de compliance
                        document.querySelector('.compliance-section').scrollIntoView({ 
                            behavior: 'smooth', 
                            block: 'center' 
                        });
                        
                        return false;
                    }
                }
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
            const parentDiv = this.closest('.switch-container');
            
            if (this.checked) {
                label.style.color = '#166534';
                label.style.fontWeight = 'bold';
                // Quitar el resaltado rojo si estaba presente
                parentDiv.style.backgroundColor = '';
                parentDiv.style.border = '';
                parentDiv.style.padding = '';
            } else {
                label.style.color = '#991b1b';
                label.style.fontWeight = 'bold';
            }
        });
    });
});