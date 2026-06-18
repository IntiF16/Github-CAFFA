const API_URL = 'http://localhost/Caffa/api.php';

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

let usuariosMemoria = [];
let partidosMemoria = [];
let prediccionesUsuario = []; 
let partidoIDTarget = null; 

window.onload = async function() {
    if (document.getElementById('contenedor-partidos')) {
        await cargarTablasUsuarios();
        await cargarPartidosIndex();
        await chequearSesionExistente();
    } 
    else if (document.getElementById('session-user-badge')) {
        await inicializarPaginaPronostico();
    }
};

async function cargarPrediccionesUsuario(userId) {
    try {
        const response = await fetch(`${API_URL}?action=get_user_predictions&user_id=${userId}`);
        prediccionesUsuario = await response.json();
    } catch (error) {
        console.error("Error al traer predicciones:", error);
        prediccionesUsuario = [];
    }
}

async function chequearSesionExistente() {
    const sesionGuardada = localStorage.getItem('usuarioPenca');
    if (sesionGuardada) {
        const usuario = JSON.parse(sesionGuardada);
        const verificado = usuariosMemoria.find(u => u.id === usuario.id);
        if (verificado) {
            localStorage.setItem('usuarioPenca', JSON.stringify(verificado));
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
        document.getElementById('status-usuario').innerHTML = `❌ El usuario "<strong>${nombreInput}</strong>" no existe.`;
        document.getElementById('status-usuario').style.color = "#ff5555";
        panel.classList.add('shake-animation');
        setTimeout(() => panel.classList.remove('shake-animation'), 400);
    }
}

function establecerInterfazLogueado(usuario) {
    const statusDiv = document.getElementById('status-usuario');
    const formInputs = document.getElementById('form-login-inputs');
    const dbAdmin = document.getElementById('dashboard-admin');
    
    const isAdmin = usuario.role && usuario.role.trim().toLowerCase() === 'admin';

    if (statusDiv) {
        statusDiv.innerHTML = `✅ ¡Conectado como <strong>${usuario.nombre}</strong> (${isAdmin ? 'Admin' : 'Jugador'})! Acumulados: ${usuario.puntaje} pts. <button class="btn-logout" onclick="cerrarSesion()">Salir</button>`;
        statusDiv.style.color = "#00ff87";
    }
    if (formInputs) formInputs.style.display = "none"; 
    
    if (dbAdmin) {
        dbAdmin.style.display = isAdmin ? "block" : "none";
    }
    
    renderizarPartidosUI(usuario);
}

function cerrarSesion() {
    localStorage.removeItem('usuarioPenca');
    location.reload();
}

async function cargarPartidosIndex() {
    try {
        const response = await fetch(`${API_URL}?action=get_matches`);
        partidosMemoria = await response.json();
    } catch (error) {
        console.error("Error cargando partidos:", error);
    }
}

function renderizarPartidosUI(usuarioActivo) {
    const contenedor = document.getElementById('contenedor-partidos');
    let html = '';

    if (!contenedor) return;
    if (partidosMemoria.length === 0) {
        contenedor.innerHTML = "<p>No hay encuentros disponibles.</p>";
        return;
    }

    const estaLogueado = usuarioActivo !== null;
    const isAdmin = estaLogueado && usuarioActivo.role && usuarioActivo.role.trim().toLowerCase() === 'admin';

    partidosMemoria.forEach(p => {
        const yaTieneResultado = (p.real_goals_team1 !== null && p.real_goals_team2 !== null);
        let stringGolesReales = yaTieneResultado ? ` (Marcador: ${p.real_goals_team1} - ${p.real_goals_team2})` : ' (Sin disputar)';
        
        let infoPartido = `
            <div class="match-info">
                <div class="teams">${p.equipoA} vs ${p.equipoB} <span style="color: var(--admin-color); font-size:0.9rem;">${stringGolesReales}</span></div>
                <div class="match-details">📅 ${p.fecha || 'Por definir'}</div>
            </div>
        `;

        if (isAdmin) {
            const valG1 = p.real_goals_team1 !== null ? p.real_goals_team1 : '';
            const valG2 = p.real_goals_team2 !== null ? p.real_goals_team2 : '';
            html += `
                <div class="match-card" style="border: 1px solid var(--admin-color);">
                    ${infoPartido}
                    <div style="display:flex; gap:6px; align-items:center;">
                        <input type="number" id="admin-g1-${p.id}" value="${valG1}" placeholder="L" style="width:35px; text-align:center; background:#000; color:#fff; border:1px solid #555; padding:4px; border-radius:4px;">
                        <span style="color:var(--admin-color)">-</span>
                        <input type="number" id="admin-g2-${p.id}" value="${valG2}" placeholder="V" style="width:35px; text-align:center; background:#000; color:#fff; border:1px solid #555; padding:4px; border-radius:4px;">
                        <button class="btn-accion btn-admin" style="padding: 6px 10px; font-size:0.8rem;" onclick="anotarResultadoReal(${p.id})">Asentar</button>
                    </div>
                </div>
            `;
        }
        else if (yaTieneResultado) {
            html += `
                <div class="match-card" style="opacity: 0.6;">
                    ${infoPartido}
                    <div><button class="btn-accion" style="background-color:#333; color:#aaa; cursor:not-allowed;" disabled>🏁 Finalizado</button></div>
                </div>
            `;
        }
        else if (estaLogueado) {
            const yaJugado = prediccionesUsuario.includes(parseInt(p.id));
            html += `
                <div class="match-card">
                    ${infoPartido}
                    <div>
                        ${yaJugado ? 
                            `<button class="btn-accion" style="background-color:#1a2421; color:#00ff87; border:1px solid rgba(0,255,135,0.4); cursor:not-allowed;" disabled>✅ Arriesgado</button>` : 
                            `<button class="btn-accion" onclick="irAPaginaPronostico(${p.id})">🎲 Pronosticar</button>`
                        }
                    </div>
                </div>
            `;
        }
        else {
            html += `
                <div class="match-card">
                    ${infoPartido}
                    <div><button class="btn-accion" style="background:#222; color:#666;" onclick="forzarAlertaCandado()">🔒 Bloqueado</button></div>
                </div>
            `;
        }
    });
    contenedor.innerHTML = html;
}

async function anotarResultadoReal(idPartido) {
    const g1 = document.getElementById(`admin-g1-${idPartido}`).value;
    const g2 = document.getElementById(`admin-g2-${idPartido}`).value;

    if (g1 === '' || g2 === '') {
        alert("Escribí los goles de ambos equipos.");
        return;
    }

    try {
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
            alert("Resultado actualizado y puntajes recalculados.");
            location.reload();
        } else {
            alert("Error: " + data.error);
        }
    } catch (e) { console.error(e); }
}

async function crearNuevoPartido() {
    const t1 = document.getElementById('new-team1').value.trim();
    const t2 = document.getElementById('new-team2').value.trim();
    const fecha = document.getElementById('new-date').value;

    if (!t1 || !t2 || !fecha) {
        alert("Completá todos los campos para dar de alta el partido.");
        return;
    }

    try {
        const response = await fetch(`${API_URL}?action=create_match`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ team1: t1, team2: t2, scheduled_at: fecha })
        });
        const data = await response.json();
        if (data.success) {
            alert("¡Partido guardado con éxito!");
            location.reload();
        } else { alert("Error al guardar: " + data.error); }
    } catch (error) { console.error(error); }
}

function forzarAlertaCandado() {
    const panel = document.getElementById('panel-identidad');
    if (panel) panel.classList.add('shake-animation');
    setTimeout(() => panel.classList.remove('shake-animation'), 400);
    document.getElementById('input-usuario').focus();
}

function irAPaginaPronostico(idPartido) {
    window.location.href = `pronosticar.html?match_id=${idPartido}`;
}

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
                contenidoTop10 += `<tr><td>#${i + 1}</td><td>${u.nombre}</td><td><strong>${u.puntaje}</strong> pts</td></tr>`;
            });
            tbodyTop10.innerHTML = contenidoTop10;
        }
    } catch (error) { console.error(error); }
}

async function inicializarPaginaPronostico() {
    const params = new URLSearchParams(window.location.search);
    partidoIDTarget = params.get('match_id');

    const sesion = localStorage.getItem('usuarioPenca');
    if (!sesion) { window.location.href = 'index.html'; return; }
    
    const usuario = JSON.parse(sesion);
    document.getElementById('session-user-badge').innerText = `👤 Jugador: ${usuario.nombre}`;

    if (!partidoIDTarget) {
        alert("Partido no especificado.");
        window.location.href = 'index.html';
        return;
    }

    try {
        const resPartidos = await fetch(`${API_URL}?action=get_matches`);
        const partidos = await resPartidos.json();
        const encuentro = partidos.find(m => parseInt(m.id) === parseInt(partidoIDTarget));

        if (!encuentro) {
            alert("No se encontró el encuentro.");
            window.location.href = 'index.html';
            return;
        }

        document.getElementById('name-local').innerText = encuentro.equipoA;
        document.getElementById('name-visitante').innerText = encuentro.equipoB;

        const codeL = flagsCode[encuentro.equipoA] || 'unknown';
        const codeV = flagsCode[encuentro.equipoB] || 'unknown';

        document.getElementById('flag-local').src = `https://flagcdn.com/w80/${codeL}.png`;
        document.getElementById('flag-visitante').src = `https://flagcdn.com/w80/${codeV}.png`;

    } catch (e) { console.error(e); }
}

async function guardarPronostico() {
    const gL = document.getElementById('input-goals-local').value;
    const gV = document.getElementById('input-goals-visitante').value;

    if (gL === '' || gV === '') {
        alert("Por favor, ingresá los goles estimados para ambos equipos.");
        return;
    }

    const usuario = JSON.parse(localStorage.getItem('usuarioPenca'));

    try {
        const response = await fetch(`${API_URL}?action=save_prediction`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                match_id: parseInt(partidoIDTarget),
                user_id: parseInt(usuario.id),
                predicted_goals_team1: parseInt(gL),
                predicted_goals_team2: parseInt(gV)
            })
        });

        const data = await response.json();
        if (data.success) {
            alert("¡Pronóstico guardado exitosamente!");
            window.location.href = 'index.html';
        } else {
            alert("Error: " + (data.error || "No se pudo guardar."));
        }
    } catch (e) {
        console.error(e);
        alert("Error de red al intentar guardar.");
    }
}