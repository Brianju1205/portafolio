const PARTICULAS = ['DE', 'DEL', 'LA', 'LAS', 'LOS', 'Y', 'MC', 'MAC', 'VON', 'VAN', 'DA', 'DAS', 'DER', 'DI', 'DIE', 'EL', 'LE', 'LES', 'MI'];
const NOMBRES_COMUNES = ['JOSE', 'J', 'J.', 'MARIA', 'MA', 'MA.', 'M', 'M.'];
const INCONVENIENTES = [
    'BUEI', 'BUEY', 'CACA', 'CACO', 'CAGA', 'CAGO', 'CAKA', 'CAKO', 'COGE', 'COJA', 'COJE', 'COJI', 'COJO', 'CULO', 'FETO', 'GUEY', 'JOTO', 'KACA', 'KACO', 'KAGA', 'KAGO', 'KOGE', 'KOJO', 'KAKA',
    'KULO', 'MAME', 'MAMO', 'MEAR', 'MEAS', 'MEON', 'MION', 'MOCO', 'MULA', 'PEDA', 'PEDO', 'PENE', 'PUTA', 'PUTO', 'QULO', 'RATA', 'RUIN'
];

function limpiar(texto) {
    return (texto || '')
        .toUpperCase()
        .replace(/Ñ/g, '#')
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/#/g, 'Ñ')
        .replace(/[^A-ZÑ ]/g, '')
        .replace(/\s+/g, ' ')
        .trim();
}

function sinParticulas(texto) {
    const palabras = texto.split(' ').filter((p) => p && !PARTICULAS.includes(p));
    return palabras.join(' ') || texto;
}

function nombreParaClave(nombre) {
    const partes = nombre.split(' ').filter(Boolean);
    if (partes.length > 1 && NOMBRES_COMUNES.includes(partes[0])) return partes.slice(1).join(' ');
    return nombre;
}

function primeraVocalInterna(texto) {
    const m = texto.slice(1).match(/[AEIOU]/);
    return m ? m[0] : 'X';
}

function homoclave(paterno, materno, nombre) {
    const completo = `${paterno} ${materno} ${nombre}`.trim();
    let cadena = '0';
    for (const c of completo) {
        if (c === ' ') cadena += '00';
        else if (c === '&' || c === 'Ñ') cadena += '10';
        else if (c >= 'A' && c <= 'I') cadena += String(c.charCodeAt(0) - 54);
        else if (c >= 'J' && c <= 'R') cadena += String(c.charCodeAt(0) - 53);
        else if (c >= 'S' && c <= 'Z') cadena += String(c.charCodeAt(0) - 51);
    }

    let suma = 0;
    for (let i = 0; i < cadena.length - 1; i++) {
        suma += Number(cadena.slice(i, i + 2)) * Number(cadena[i + 1]);
    }

    const tabla = '123456789ABCDEFGHIJKLMNPQRSTUVWXYZ';
    const resto = suma % 1000;
    return tabla[Math.floor(resto / 34)] + tabla[resto % 34];
}

function digitoVerificador(rfc12) {
    const valores = '0123456789ABCDEFGHIJKLMN&OPQRSTUVWXYZ Ñ';
    let suma = 0;
    for (let i = 0; i < 12; i++) {
        suma += valores.indexOf(rfc12[i]) * (13 - i);
    }
    const residuo = suma % 11;
    if (residuo === 0) return '0';
    const dv = 11 - residuo;
    return dv === 10 ? 'A' : String(dv);
}

export function calcularRFC({ nombre, paterno, materno, fecha }) {
    const n = limpiar(nombre);
    const p = limpiar(paterno);
    const m = limpiar(materno);

    const pc = sinParticulas(p);
    const mc = sinParticulas(m);
    const nc = nombreParaClave(sinParticulas(n));

    let letras;
    if (!mc) {
        letras = pc.slice(0, 2) + nc.slice(0, 2);
    } else if (pc.length <= 2) {
        letras = pc[0] + mc[0] + nc.slice(0, 2);
    } else {
        letras = pc[0] + primeraVocalInterna(pc) + mc[0] + nc[0];
    }
    letras = letras.replace(/Ñ/g, 'X');
    if (INCONVENIENTES.includes(letras)) letras = letras.slice(0, 3) + 'X';

    const yy = String(fecha.getFullYear()).slice(-2);
    const mm = String(fecha.getMonth() + 1).padStart(2, '0');
    const dd = String(fecha.getDate()).padStart(2, '0');

    const base = letras + yy + mm + dd + homoclave(p, m, n);
    return base + digitoVerificador(base);
}
