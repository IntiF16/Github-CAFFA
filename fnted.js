// URL de tu backend apuntando a la carpeta de tu proyecto en XAMPP
const API_URL = 'http://localhost/Caffa/api.php';

// Diccionario completo de países para el Mundial 2026
const flagsCode = {
    "Argelia": "dz", "Argentina": "ar", "Australia": "au", "Austria": "at", "Bélgica": "be", "Bosnia": "ba",
    "Brasil": "br", "Canadá": "ca", "Costa de Marfil": "ci", "RD del Congo": "cd", "Colombia": "co", "Cabo Verde": "cv",
    "Croacia": "hr", "Curazao": "cw", "Chequia": "cz", "Ecuador": "ec", "Egipto": "eg", "Inglaterra": "gb-eng",
    "España": "es", "Francia": "fr", "Alemania": "de", "Ghana": "gh", "Haití": "ht", "Irán": "ir",
    "Irak": "iq", "Jordania": "jo", "Japón": "jp", "Corea del Sur": "kr", "Arabia Saudí": "sa", "Marruecos": "ma",
    "México": "mx", "Países Bajos": "nl", "Noruega": "no", "Nueva Zelanda": "nz", "Panamá": "pa", "Paraguay": "py",
    "Portugal": "pt", "Catar": "qa", "Sudáfrica": "za", "Escocia": "gb-sct", "Senegal": "sn", "Suiza": "ch",
    "Suecia": "se", "Túnez": "tn", "Turquía": "tr", "Uruguay": "uy", "Estados Unidos": "us", "Uzbekistán": "uz"
};

// Variables globales en memoria
let usuariosMemoria = [];
let partidosMemoria = [];
let prediccionesUsuario = []; 
let partidoIDTarget = null; 

// === LLAMADO INICIAL DE LA PÁGINA (DETECTA EN QUÉ HTML ESTAMOS) ===
window.onload = async function() {
    if (document.getElementById('contenedor-partidos')) {
        // --- ESTAMOS EN INDEX.HTML ---
        await cargarTablasUsuarios();
        await cargarPartidosIndex();
        await chequearSesionExistente();
    } 
    else if (document.getElementById('session-user-badge')) {
        // --- ESTAMOS EN PRONOSTICAR.HTML ---
        await inicializarPaginaPronostico();
    }
};

// === HISTORIAL DE PRONÓSTICOS DE LA BD ===
async function cargarPrediccionesUsuario(userId) {
    try {
        const response = await fetch(`${API_URL}?action=get_user_predictions&user_id=${userId}`);
        prediccionesUsuario = await response.json();
    } catch (error) {
        console.error("Error al cargar predicciones del usuario:", error);
        prediccionesUsuario = [];
    }
}

// === GESTIÓN DE SESIÓN EN INDEX.HTML ===
async function chequearSesionExistente() {
    const sesionGuardada = localStorage.getItem('usuarioPenca');
    if (sesionGuardada) {
        const usuario = JSON.parse(sesionGuardada);
        const verificado = usuariosMemoria.find(u => u.id === usuario.id);
        if (verificado) {
            await cargarPrediccionesUsuario(verificado.id);
            establecerInterfazLogueado(verificado);
            return;
        } else {
            localStorage.removeItem('usuarioPenca');
        }
    }
    renderizarPartidosUI(null);
}

async function verificarUsuario() {
    const nombreInput = document.getElementById('input-usuario').value.trim();
    const panel = document.getElementById('panel-identidad');

    if (!nombreInput) {
        alert("Escribí tu nombre antes de ingresar.");
        return;
    }

    const encontrado = usuariosMemoria.find(u => u.nombre.toLowerCase() === nombreInput.toLowerCase());

    if (encontrado) {
        localStorage.setItem('usuarioPenca', JSON.stringify(encontrado));
        await cargarPrediccionesUsuario(encontrado.id);
        establecerInterfazLogueado(encontrado);
    } else {
        localStorage.removeItem('usuarioPenca');
        document.getElementById('status-usuario').innerHTML = `❌ El usuario "<strong>${nombreInput}</strong>" no existe en la BD.`;
        document.getElementById('status-usuario').style.color = "#ff5555";
        
        panel.classList.add('shake-animation');
        setTimeout(() => panel.classList.remove('shake-animation'), 400);
    }
}

function establecerInterfazLogueado(usuario) {
    const statusDiv = document.getElementById('status-usuario');
    const formInputs = document.getElementById('form-login-inputs');
    
    if (statusDiv) {
        statusDiv.innerHTML = `✅ ¡Conectado como <strong>${usuario.nombre}</strong> (${usuario.role === 'admin' ? 'Admin' : 'Jugador'})! Acumulados: ${usuario.puntaje} pts. <button class="btn-logout" onclick="cerrarSesion()">Salir</button>`;
        statusDiv.style.color = "#00ff87";
    }
    if (formInputs) formInputs.style.display = "none"; 
    
    renderizarPartidosUI(usuario);
}

function cerrarSesion() {
    localStorage.removeItem('usuarioPenca');
    location.reload();
}

// === RENDERIZAR PARTIDOS EN INDEX.HTML ===
async function cargarPartidosIndex() {
    try {
        const response = await fetch(`${API_URL}?action=get_matches`);
        partidosMemoria = await response.json();
    } catch (error) {
        console.error("Error al traer los partidos:", error);
    }
}

function renderizarPartidosUI(usuarioActivo) {
    const contenedor = document.getElementById('contenedor-partidos');
    let html = '';

    if (!contenedor) return;

    if (partidosMemoria.length === 0) {
        contenedor.innerHTML = "<p>No hay encuentros deportivos disponibles.</p>";
        return;
    }

    const estaLogueado = usuarioActivo !== null;
    const isAdmin = estaLogueado && (usuarioActivo.role === 'admin' || usuarioActivo.nombre.toLowerCase() === 'admin');

    partidosMemoria.forEach(p => {
        const yaTieneResultado = (p.real_goals_team1 !== null && p.real_goals_team2 !== null);
        
        let stringGolesReales = yaTieneResultado ? ` (Resultado: ${p.real_goals_team1} - ${p.real_goals_team2})` : '';
        let infoPartido = `
            <div class="match-info">
                <div class="teams">${p.equipoA} vs ${p.equipoB} <span style="color: var(--accent-color);">${stringGolesReales}</span></div>
                <div class="match-details">📅 ${p.fecha || 'Por definir'}</div>
            </div>
        `;

        // ESCENARIO A: Si ya tiene un resultado real guardado en la base de datos
        if (yaTieneResultado) {
            html += `
                <div class="match-card" style="opacity: 0.7;">
                    ${infoPartido}
                    <div>
                        <button class="btn-accion" style="background-color: #333; color: #aaa; cursor: not-allowed;" disabled>
                            🏁 Finalizado
                        </button>
                    </div>
                </div>
            `;
        }
        // ESCENARIO B: El usuario conectado es Administrador (Muestra el cargador de goles reales)
        else if (isAdmin) {
            html += `
                <div class="match-card" style="border: 1px dashed var(--accent-color);">
                    ${infoPartido}
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <input type="number" id="admin-g1-${p.id}" placeholder="L" style="width: 40px; text-align: center; background: #000; color: #fff; border: 1px solid var(--border-color); padding: 5px; border-radius: 4px;">
                        <span style="color: var(--accent-color)">-</span>
                        <input type="number" id="admin-g2-${p.id}" placeholder="V" style="width: 40px; text-align: center; background: #000; color: #fff; border: 1px solid var(--border-color); padding: 5px; border-radius: 4px;">
                        <button class="btn-accion" style="padding: 6px 12px; font-size: 0.85rem;" onclick="anotarResultadoReal(${p.id})">
                            Guardar Result.
                        </button>
                    </div>
                </div>
            `;
        }
        // ESCENARIO C: Usuario común logueado
        else if (estaLogueado) {
            const yaJugado = prediccionesUsuario.includes(parseInt(p.id));

            if (yaJugado) {
                html += `
                    <div class="match-card">
                        ${infoPartido}
                        <div>
                            <button class="btn-accion" style="background-color: #1a2421; color: #00ff87; border: 1px solid rgba(0,255,135,0.4); cursor: not-allowed;" disabled>
                                ✅ Completado
                            </button>
                        </div>
                    </div>
                `;
            } else {
                html += `
                    <div class="match-card">
                        ${infoPartido}
                        <div>
                            <button class="btn-accion" onclick="irAPaginaPronostico(${p.id})">
                                🎲 Pronosticar
                            </button>
                        </div>
                    </div>
                `;
            }
        } 
        // ESCENARIO D: Sin iniciar sesión (Candado)
        else {
            html += `
                <div class="match-card">
                    ${infoPartido}
                    <div>
                        <button class="btn-accion btn-locked" onclick="forzarAlertaCandado()">
                            <span class="lock-icon">🔒</span> Cuenta Requerida
                        </button>
                    </div>
                </div>
            `;
        }
    });
    contenedor.innerHTML = html;
}

// Acción del admin para cerrar el encuentro enviando los goles reales
async function anotarResultadoReal(idPartido) {
    const g1 = document.getElementById(`admin-g1-${idPartido}`).value;
    const g2 = document.getElementById(`admin-g2-${idPartido}`).value;

    if (g1 === '' || g2 === '') {
        alert("Debes rellenar los goles de ambos equipos antes de guardar.");
        return;
    }

    try {
        // Corregido: URL limpia y sintaxis del fetch cerrada correctamente
        const response = await fetch(`${API_URL}?action=settle_match`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                match_id: parseInt(idPartido),
                real_goals_team1: parseInt(g1),
                real_goals_team2: parseInt(g2)
            })
        });
        
        const data = await response.json();
        if (data.success) {
            alert("Resultado asentado y puntos distribuidos con éxito.");
            location.reload(); 
        } else {
            alert("Error: " + data.error);
        }
    } catch (e) {
        console.error("Error al guardar resultado real:", e);
    }
}

function forzarAlertaCandado() {
    const panel = document.getElementById('panel-identidad');
    if (panel) panel.classList.add('shake-animation');
    setTimeout(() => panel.classList.remove('shake-animation'), 400);
    const inputUser = document.getElementById('input-usuario');
    if (inputUser) inputUser.focus();
}

function irAPaginaPronostico(idPartido) {
    window.location.href = `pronosticar.html?match_id=${idPartido}`;
}

// === LÓGICA DE DETALLE EN PRONOSTICAR.HTML ===
async function inicializarPaginaPronostico() {
    const sesion = localStorage.getItem('usuarioPenca');
    if (!sesion) {
        alert("⚠️ Acceso denegado: Primero tenés que ingresar tu nombre en la página de inicio.");
        volverAlInicio();
        return;
    }
    const usuario = JSON.parse(sesion);
    document.getElementById('session-user-badge').innerText = `Jugador activo: ${usuario.nombre} (ID: ${usuario.id})`;

    const urlParams = new URLSearchParams(window.location.search);
    partidoIDTarget = urlParams.get('match_id');

    if (!partidoIDTarget) {
        alert("No se seleccionó ningún partido válido.");
        volverAlInicio();
        return;
    }

    await cargarPrediccionesUsuario(usuario.id);
    if (prediccionesUsuario.includes(parseInt(partidoIDTarget))) {
        alert("⚠️ Ya registraste un pronóstico para este partido anteriormente.");
        volverAlInicio();
        return;
    }

    try {
        const response = await fetch(`${API_URL}?action=get_matches`);
        const partidos = await response.json();
        const partidoActivo = partidos.find(p => p.id == partidoIDTarget);

        if (!partidoActivo) {
            alert("El partido solicitado no existe.");
            volverAlInicio();
            return;
        }

        document.getElementById('name-local').innerText = partidoActivo.equipoA;
        document.getElementById('name-visitante').innerText = partidoActivo.equipoB;

        const codeA = flagsCode[partidoActivo.equipoA] || "un";
        const codeB = flagsCode[partidoActivo.equipoB] || "un";
        document.getElementById('flag-local').src = `https://flagcdn.com/w160/${codeA}.png`;
        document.getElementById('flag-visitante').src = `https://flagcdn.com/w160/${codeB}.png`;

    } catch (e) {
        console.error("Error cargando detalles en el formulario:", e);
    }
}

async function guardarPrediccionPagina() {
    const golesA = document.getElementById('input-goals-local').value;
    const golesB = document.getElementById('input-goals-visitante').value;
    const usuario = JSON.parse(localStorage.getItem('usuarioPenca'));

    if (golesA === '' || golesB === '') {
        alert("Por favor cargá los goles de ambos equipos.");
        return;
    }

    try {
        const response = await fetch(`${API_URL}?action=save_prediction`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                match_id: parseInt(partidoIDTarget),
                user_id: usuario.id,
                predicted_goals_team1: parseInt(golesA),
                predicted_goals_team2: parseInt(golesB)
            })
        });

        const data = await response.json();
        if (data.success) {
            alert("¡Pronóstico guardado con éxito! Redirigiendo al inicio...");
            volverAlInicio();
        } else {
            alert("Error: " + data.error);
            volverAlInicio();
        }
    } catch (error) {
        console.error("Error enviando datos:", error);
    }
}

function volverAlInicio() {
    window.location.href = 'index.html';
}

// === TABLAS DE POSICIONES ===
async function cargarTablasUsuarios() {
    try {
        const resTodos = await fetch(`${API_URL}?action=get_users`);
        usuariosMemoria = await resTodos.json(); 
        
        const tbodyTodos = document.getElementById('tabla-todos');
        if (tbodyTodos) {
            let contenidoTodos = '';
            usuariosMemoria.forEach(u => {
                contenidoTodos += `<tr><td>${u.nombre}</td><td>${u.puntaje} pts</td></tr>`;
            });
            tbodyTodos.innerHTML = contenidoTodos;
        }

        const resTop10 = await fetch(`${API_URL}?action=get_top10`);
        const usuariosTop10 = await resTop10.json();
        
        const tbodyTop10 = document.getElementById('tabla-top10');
        if (tbodyTop10) {
            let contenidoTop10 = '';
            usuariosTop10.forEach((u, i) => {
                contenidoTop10 += `
                    <tr>
                        <td class="posicion">#${i + 1}</td>
                        <td>${u.nombre}</td>
                        <td><strong>${u.puntaje}</strong> pts</td>
                    </tr>`;
            });
            tbodyTop10.innerHTML = contenidoTop10;
        }
    } catch (error) {
        console.error("Error al cargar listado de usuarios:", error);
    }
}