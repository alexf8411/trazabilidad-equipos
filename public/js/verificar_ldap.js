/**
 * public/js/verificar_ldap.js
 * Lógica para consultar el Directorio Activo mediante sAMAccountName
 */
function verificarUsuario() {
    const userField = document.getElementById('user_id');
    const user = userField.value.trim();
    const card = document.getElementById('userCard');
    const btnSubmit = document.getElementById('btnSubmit');
    const lblNombre = document.getElementById('ldap_nombre');
    const lblInfo = document.getElementById('ldap_info');
    const hiddenEmail = document.getElementById('correo_resp_real');

    if (!user) {
        alert("⚠️ Por favor, ingrese el Usuario Institucional (ej: nombre.apellido).");
        return;
    }

    // Estado visual de carga
    card.style.display = 'block';
    card.style.opacity = '0.6';
    lblNombre.innerText = "Consultando...";
    lblInfo.innerText = "Buscando en Directorio Activo...";

    fetch(`../core/validar_usuario_ldap.php?usuario=${encodeURIComponent(user)}`)
        .then(response => {
            if (!response.ok) throw new Error('Error de red');
            return response.json();
        })
        .then(data => {
            card.style.opacity = '1';
            if(data.status === 'success') {
                // ÉXITO: Mostramos los datos reales
                lblNombre.innerText = data.nombre;
                lblInfo.innerHTML = `<strong>Email:</strong> ${data.correo}<br><strong>Depto:</strong> ${data.departamento}`;
                
                // Asignamos el correo real para la base de datos
                hiddenEmail.value = data.correo;
                
                // Habilitamos el botón de guardado
                btnSubmit.disabled = false;
                btnSubmit.style.cursor = "pointer";
            } else {
                // FALLO: El usuario no existe o error de LDAP
                alert("❌ El usuario '" + user + "' no fue localizado en la Universidad.");
                card.style.display = 'none';
                btnSubmit.disabled = true;
                hiddenEmail.value = "";
            }
        })
        .catch(err => {
            console.error("Error Fetch:", err);
            alert("❌ Fallo en la conexión con el servidor de validación.");
            card.style.display = 'none';
        });
}