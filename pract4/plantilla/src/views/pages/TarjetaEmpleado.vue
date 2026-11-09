<script setup>
import { calcularRFC } from '@/service/rfc';
import JsBarcode from 'jsbarcode';
import { useToast } from 'primevue/usetoast';
import { computed, nextTick, onMounted, reactive, ref } from 'vue';

const toast = useToast();

const form = reactive({
    nombre: '',
    paterno: '',
    materno: '',
    fecha: null,
    sexo: 'Mujer',
    rfc: ''
});

const sexos = ['Mujer', 'Hombre'];
const enviado = ref(false);
const foto = ref(null);
const barcode = ref(null);

const tarjeta = reactive({
    nombre: 'NOMBRE',
    apellidos: 'APELLIDOS',
    puesto: 'Empleada',
    rfc: 'XXXX000000XXX'
});

const errores = computed(() => ({
    nombre: enviado.value && !form.nombre.trim(),
    paterno: enviado.value && !form.paterno.trim(),
    fecha: enviado.value && !form.fecha
}));

function dibujarCodigo(valor) {
    JsBarcode(barcode.value, valor, {
        format: 'CODE128',
        width: 1.4,
        height: 42,
        fontSize: 11,
        margin: 0,
        background: 'transparent',
        lineColor: '#0b1f3a'
    });
}

function generar() {
    enviado.value = true;
    if (!form.nombre.trim() || !form.paterno.trim() || !form.fecha) {
        toast.add({ severity: 'warn', summary: 'Faltan datos', detail: 'Nombre, apellido paterno y fecha de nacimiento son obligatorios', life: 3000 });
        return;
    }

    form.rfc = calcularRFC(form);

    tarjeta.nombre = form.nombre.trim().toUpperCase();
    tarjeta.apellidos = `${form.paterno} ${form.materno}`.trim().toUpperCase();
    tarjeta.puesto = form.sexo === 'Mujer' ? 'Empleada' : 'Empleado';
    tarjeta.rfc = form.rfc;

    nextTick(() => dibujarCodigo(form.rfc));
    toast.add({ severity: 'success', summary: 'Tarjeta generada', detail: `RFC: ${form.rfc}`, life: 3000 });
}

function limpiar() {
    Object.assign(form, { nombre: '', paterno: '', materno: '', fecha: null, sexo: 'Mujer', rfc: '' });
    Object.assign(tarjeta, { nombre: 'NOMBRE', apellidos: 'APELLIDOS', puesto: 'Empleada', rfc: 'XXXX000000XXX' });
    foto.value = null;
    enviado.value = false;
    dibujarCodigo(tarjeta.rfc);
}

function cargarFoto(event) {
    const archivo = event.files?.[0];
    if (!archivo) return;
    const lector = new FileReader();
    lector.onload = () => (foto.value = lector.result);
    lector.readAsDataURL(archivo);
}

onMounted(() => dibujarCodigo(tarjeta.rfc));
</script>

<template>
    <div class="grid grid-cols-12 gap-8">
        <div class="col-span-12 lg:col-span-7">
            <div class="card flex flex-col gap-5">
                <div class="font-semibold text-xl uppercase">Generar el RFC de una persona</div>

                <div class="flex flex-col gap-2">
                    <label for="nombre">Nombre(s):</label>
                    <InputText id="nombre" v-model="form.nombre" :invalid="errores.nombre" placeholder="Ej. Susana" />
                </div>
                <div class="flex flex-col gap-2">
                    <label for="paterno">Apellido Paterno:</label>
                    <InputText id="paterno" v-model="form.paterno" :invalid="errores.paterno" placeholder="Ej. Rojas" />
                </div>
                <div class="flex flex-col gap-2">
                    <label for="materno">Apellido Materno:</label>
                    <InputText id="materno" v-model="form.materno" placeholder="Ej. Merida" />
                </div>
                <div class="flex flex-col md:flex-row gap-5">
                    <div class="flex flex-col gap-2 flex-1">
                        <label for="fecha">Fecha Nacimiento:</label>
                        <DatePicker id="fecha" v-model="form.fecha" :invalid="errores.fecha" dateFormat="dd/mm/yy" showIcon :maxDate="new Date()" placeholder="dd/mm/aaaa" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label>Sexo:</label>
                        <SelectButton v-model="form.sexo" :options="sexos" :allowEmpty="false" />
                    </div>
                </div>
                <div class="flex flex-col gap-2">
                    <label>Fotografía (opcional):</label>
                    <FileUpload mode="basic" accept="image/*" :maxFileSize="3000000" chooseLabel="Elegir foto" auto customUpload @select="cargarFoto" />
                </div>
                <div class="flex flex-col gap-2">
                    <label for="rfc">RFC:</label>
                    <InputText id="rfc" v-model="form.rfc" readonly placeholder="Se calcula al presionar Generar" class="font-semibold tracking-wider" />
                </div>

                <div class="flex flex-wrap gap-3">
                    <Button label="Generar" icon="pi pi-id-card" severity="success" class="w-full sm:w-48" @click="generar" />
                    <Button label="Limpiar" icon="pi pi-eraser" severity="secondary" outlined class="w-full sm:w-36" @click="limpiar" />
                </div>
            </div>
        </div>

        <div class="col-span-12 lg:col-span-5">
            <div class="card">
                <div class="font-semibold text-xl uppercase mb-6">Tarjeta digital</div>

                <div class="id-card">
                    <svg class="id-card__top" viewBox="0 0 300 190" preserveAspectRatio="none">
                        <path d="M0 0H300V120C240 190 60 190 0 120Z" fill="#1565c0" />
                        <path d="M0 0H300V100C240 165 60 165 0 100Z" fill="#0b1f3a" />
                    </svg>

                    <div class="id-card__brand">
                        <i class="pi pi-building-columns"></i>
                        <div>
                            <div class="id-card__company">UNACH</div>
                            <div class="id-card__slogan">Escuela de Tecnologías Digitales Aplicadas</div>
                        </div>
                    </div>

                    <div class="id-card__photo">
                        <img v-if="foto" :src="foto" alt="Fotografía del empleado" />
                        <svg v-else viewBox="0 0 100 100">
                            <rect width="100" height="100" fill="#e9eef5" />
                            <circle cx="50" cy="38" r="18" fill="#0b1f3a" />
                            <path v-if="tarjeta.puesto === 'Empleada'" d="M32 40C30 20 70 20 68 40L72 62H28Z" fill="#0b1f3a" />
                            <path d="M18 100C18 72 34 62 50 62S82 72 82 100Z" fill="#0b1f3a" />
                            <path d="M45 62H55L50 78Z" fill="#e9eef5" />
                        </svg>
                    </div>

                    <div class="id-card__body">
                        <div class="id-card__name">{{ tarjeta.nombre }}</div>
                        <div class="id-card__name">{{ tarjeta.apellidos }}</div>
                        <div class="id-card__role">{{ tarjeta.puesto }}</div>
                        <div class="id-card__id">ID : {{ tarjeta.rfc }}</div>
                        <svg ref="barcode" class="id-card__barcode"></svg>
                    </div>

                    <svg class="id-card__bottom" viewBox="0 0 300 70" preserveAspectRatio="none">
                        <path d="M0 30C80 0 220 0 300 30V70H0Z" fill="#1565c0" />
                        <path d="M0 45C80 18 220 18 300 45V70H0Z" fill="#0b1f3a" />
                    </svg>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.id-card {
    position: relative;
    width: 100%;
    max-width: 300px;
    margin: 0 auto;
    aspect-ratio: 300 / 480;
    background: #ffffff;
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(11, 31, 58, 0.25);
    display: flex;
    flex-direction: column;
    align-items: center;
    color: #0b1f3a;
}

.id-card__top {
    position: absolute;
    inset: 0 0 auto 0;
    width: 100%;
    height: 40%;
}

.id-card__bottom {
    position: absolute;
    inset: auto 0 0 0;
    width: 100%;
    height: 14%;
}

.id-card__brand {
    position: relative;
    margin-top: 7%;
    display: flex;
    align-items: center;
    gap: 0.6rem;
    color: #fff;
}

.id-card__brand .pi {
    font-size: 1.6rem;
}

.id-card__company {
    font-weight: 700;
    letter-spacing: 0.08em;
    font-size: 0.95rem;
}

.id-card__slogan {
    font-size: 0.7rem;
    opacity: 0.8;
}

.id-card__photo {
    position: relative;
    margin-top: 7%;
    width: 38%;
    aspect-ratio: 1;
    border-radius: 50%;
    border: 6px solid #fff;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.2);
    overflow: hidden;
    background: #e9eef5;
}

.id-card__photo img,
.id-card__photo svg {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.id-card__body {
    position: relative;
    margin-top: 5%;
    padding: 0 1rem;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.id-card__name {
    font-weight: 800;
    font-size: 1.15rem;
    line-height: 1.25;
}

.id-card__role {
    margin-top: 0.15rem;
    color: #64748b;
    font-size: 0.95rem;
}

.id-card__id {
    margin-top: 0.6rem;
    font-weight: 600;
    font-size: 0.85rem;
    letter-spacing: 0.04em;
}

.id-card__barcode {
    margin-top: 0.5rem;
    max-width: 85%;
    height: auto;
}
</style>
