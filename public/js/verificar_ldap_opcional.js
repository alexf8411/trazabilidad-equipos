/**
 * public/js/verificar_ldap_opcional.js
 * Lógica para el Responsable Secundario (Opcional)
 */
function verificarUsuarioOpcional() {
    const userField = document.getElementById('user_id_sec'); // ID Secundario
    const userInput = userField.value.trim();
    const card = document.getElementById('userCard_sec');    // ID Secundario
    const lblNombre = document.getElementById('ldap_nombre_sec');
    const lblInfo = document.getElementById('ldap_info_sec');
    const hiddenEmail = document.getElementById('correo_sec_real'); // Campo oculto secundario

    if (!userInput) {
        alert("⚠️ Por favor, ingrese el Usuario Institucional para el responsable secundario.");
        return;
    }

    let usuarioLimpio = userInput.split('@')[0];

    // Estado visual: Consultando
    card.style.display = 'block';
    card.style.opacity = '0.7';
    lblNombre.innerText = "Consultando...";
    lblInfo.innerText = "Buscando en Directorio Activo...";

    fetch('api_ldap.php?usuario=' + encodeURIComponent(usuarioLimpio))
        .then(response => {
            if (!response.ok) throw new Error('Error de red');
            return response.json();
        })
        .then(data => {
            card.style.opacity = '1';
            
            if (data.status === 'success') {
                lblNombre.style.color = "#475569"; // Color más suave para el secundario
                lblNombre.innerText = data.nombre;
                
                lblInfo.innerHTML = `
                    <div style="margin-top:5px; padding:5px; background:#e0f2fe; color:#0369a1; border-radius:4px; font-size:0.75rem; display:inline-block; font-weight:bold;">✅ RESPONSABLE ASOCIADO</div>
                    <div style="margin-top:8px;">
                        <strong>Email:</strong> ${data.correo}<br>
                        <strong>Cargo:</strong> ${data.departamento}
                    </div>
                `;
                
                hiddenEmail.value = data.correo;
                // NOTA: No tocamos el btnSubmit aquí para que no dependa de este campo opcional
            } else {
                alert("❌ El usuario '" + usuarioLimpio + "' no existe.");
                card.style.display = 'none';
                hiddenEmail.value = "";
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert("❌ Error de comunicación LDAP.");
            card.style.display = 'none';
        });
}