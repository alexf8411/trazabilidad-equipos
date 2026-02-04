/**
 * public/js/verificar_ldap.js
 * Lógica para verificar identidad en AD (Soporta usuario corto o correo completo)
 */
function verificarUsuario() {
    const userField = document.getElementById('user_id');
    const userInput = userField.value.trim();
    const card = document.getElementById('userCard');
    const btnSubmit = document.getElementById('btnSubmit');
    const lblNombre = document.getElementById('ldap_nombre');
    const lblInfo = document.getElementById('ldap_info');
    const hiddenEmail = document.getElementById('correo_resp_real');

    if (!userInput) {
        alert("⚠️ Por favor, ingrese el Usuario Institucional.");
        return;
    }

    // --- MAGIA AQUÍ: Limpieza de input ---
    // Si el técnico pega "guillermo@lab.urosario.edu.co", nos quedamos con "guillermo"
    // Si escribe "guillermo", se queda igual.
    let usuarioLimpio = userInput.split('@')[0];

    // Estado visual: Cargando
    card.style.display = 'block';
    card.style.opacity = '0.7';
    lblNombre.innerText = "Consultando...";
    lblInfo.innerText = "Conectando con Directorio Activo...";

    // Llamada a la API
    fetch('api_ldap.php?usuario=' + encodeURIComponent(usuarioLimpio))
        .then(response => {
            if (!response.ok) throw new Error('Error de red');
            return response.json();
        })
        .then(data => {
            card.style.opacity = '1';
            
            if (data.status === 'success') {
                // ÉXITO: Usuario encontrado
                lblNombre.style.color = "var(--primary)";
                lblNombre.innerText = data.nombre;
                
                lblInfo.innerHTML = `
                    <div style="margin-top:5px; padding:5px; background:#dcfce7; color:#166534; border-radius:4px; font-size:0.75rem; display:inline-block; font-weight:bold;">✅ IDENTIDAD CONFIRMADA</div>
                    <div style="margin-top:8px;">
                        <strong>Email:</strong> ${data.correo}<br>
                        <strong>Cargo/Depto:</strong> ${data.departamento}
                    </div>
                `;
                
                // Guardamos el correo real para la Base de Datos
                hiddenEmail.value = data.correo;
                
                // Habilitamos el botón de guardar
                btnSubmit.disabled = false;
                btnSubmit.style.opacity = "1";
                btnSubmit.style.cursor = "pointer";
            } else {
                // ERROR: Usuario no existe
                alert("❌ El usuario '" + usuarioLimpio + "' no existe en la Universidad.\nVerifique los datos.");
                card.style.display = 'none';
                btnSubmit.disabled = true;
                hiddenEmail.value = "";
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert("❌ Error de comunicación con el servidor LDAP.");
            card.style.display = 'none';
        });
}