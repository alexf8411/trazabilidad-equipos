/**
 * public/js/verificar_ldap.js
 * Basado en la lógica de session-check.js para evitar errores de ruta
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
        alert("⚠️ Por favor, ingrese el Usuario Institucional.");
        return;
    }

    // Efecto visual de carga
    card.style.display = 'block';
    lblNombre.innerText = "Consultando...";
    lblInfo.innerText = "Buscando en Directorio Activo...";

    // Llamamos a api_ldap.php (que ahora está en la misma carpeta pública)
    fetch('api_ldap.php?usuario=' + encodeURIComponent(user))
        .then(response => {
            if (!response.ok) throw new Error('Error de servidor');
            return response.json();
        })
        .then(data => {
            if (data.status === 'success') {
                lblNombre.innerText = data.nombre;
                lblInfo.innerHTML = `<strong>Email:</strong> ${data.correo}<br><strong>Depto:</strong> ${data.departamento}`;
                hiddenEmail.value = data.correo;
                btnSubmit.disabled = false;
                btnSubmit.style.opacity = "1";
            } else {
                alert("❌ Usuario '" + user + "' no encontrado.");
                card.style.display = 'none';
                btnSubmit.disabled = true;
            }
        })
        .catch(error => {
            console.error('Error LDAP:', error);
            alert("❌ Fallo en la comunicación con el validador LDAP.");
            card.style.display = 'none';
        });
}