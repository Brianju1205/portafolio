const btnActualizar = document.getElementById('btn-actualizar');
const tarjetas = document.querySelectorAll('.tarjeta');

const API_URL = 'https://api.exchangerate-api.com/v4/latest/MXN';

async function actualizarPrecios() {
  btnActualizar.textContent = 'Cargando...';
  btnActualizar.disabled = true;

  try {
    const respuesta = await fetch(API_URL);
    const datos = await respuesta.json();

    const tasaUSD = datos.rates.USD;
    const tasaJPY = datos.rates.JPY;

    tarjetas.forEach(tarjeta => {
      const precioMXN = parseFloat(tarjeta.getAttribute('data-precio-mxn'));

      const precioUSD = (precioMXN * tasaUSD).toFixed(2);
      const precioJPY = Math.round(precioMXN * tasaJPY).toLocaleString();

      tarjeta.querySelector('.precio-usd').textContent = `$${precioUSD}`;
      tarjeta.querySelector('.precio-jpy').textContent = `¥${precioJPY}`;
    });

    btnActualizar.textContent = 'Precios Actualizados';

    setTimeout(() => {
      btnActualizar.textContent = 'Actualizar Precios';
      btnActualizar.disabled = false;
    }, 2000);

  } catch (error) {
    console.error(error);
    alert('Error al consultar el tipo de cambio. Revisa tu conexión.');
    btnActualizar.textContent = 'Actualizar Precios';
    btnActualizar.disabled = false;
  }
}

btnActualizar.addEventListener('click', actualizarPrecios);
