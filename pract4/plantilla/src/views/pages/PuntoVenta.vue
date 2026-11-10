<script setup>
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { computed, reactive, ref } from 'vue';

const toast = useToast();
const confirm = useConfirm();

const IVA = 0.16;

const productos = ref([
    { id: 1, nombre: 'Impresora LaserJet Color', precio: 5200, cantidad: 2 },
    { id: 2, nombre: 'Monitor LED 31 plg.', precio: 1700, cantidad: 3 }
]);
let siguienteId = 3;

const form = reactive({ nombre: '', cantidad: null, precio: null });
const enviado = ref(false);

const dialogo = ref(false);
const edicion = reactive({ id: null, nombre: '', cantidad: null, precio: null });

const subtotal = computed(() => productos.value.reduce((suma, p) => suma + p.precio * p.cantidad, 0));
const iva = computed(() => subtotal.value * IVA);
const total = computed(() => subtotal.value + iva.value);

function moneda(valor) {
    return valor.toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });
}

function valido(datos) {
    return datos.nombre.trim() && datos.cantidad > 0 && datos.precio > 0;
}

function registrar() {
    enviado.value = true;
    if (!valido(form)) {
        toast.add({ severity: 'warn', summary: 'Faltan datos', detail: 'Captura nombre, cantidad y precio unitario', life: 3000 });
        return;
    }

    productos.value.push({ id: siguienteId++, nombre: form.nombre.trim(), precio: form.precio, cantidad: form.cantidad });
    toast.add({ severity: 'success', summary: 'Registrado', detail: form.nombre.trim(), life: 2500 });

    form.nombre = '';
    form.cantidad = null;
    form.precio = null;
    enviado.value = false;
}

function editar(producto) {
    Object.assign(edicion, producto);
    dialogo.value = true;
}

function guardar() {
    if (!valido(edicion)) {
        toast.add({ severity: 'warn', summary: 'Datos inválidos', detail: 'Revisa los campos', life: 3000 });
        return;
    }

    const producto = productos.value.find((p) => p.id === edicion.id);
    Object.assign(producto, { nombre: edicion.nombre.trim(), precio: edicion.precio, cantidad: edicion.cantidad });
    dialogo.value = false;
    toast.add({ severity: 'info', summary: 'Actualizado', detail: producto.nombre, life: 2500 });
}

function eliminar(producto) {
    confirm.require({
        message: `¿Eliminar "${producto.nombre}"?`,
        header: 'Confirmar',
        icon: 'pi pi-exclamation-triangle',
        rejectProps: { label: 'Cancelar', severity: 'secondary', outlined: true },
        acceptProps: { label: 'Eliminar', severity: 'danger' },
        accept: () => {
            productos.value = productos.value.filter((p) => p.id !== producto.id);
            toast.add({ severity: 'error', summary: 'Eliminado', detail: producto.nombre, life: 2500 });
        }
    });
}
</script>

<template>
    <div class="card">
        <div class="font-semibold text-xl mb-6">PUNTO DE VENTA (POS)</div>

        <div class="flex flex-wrap items-start gap-3 p-4 mb-4 border border-surface rounded-border">
            <div class="flex flex-col gap-1 flex-1 min-w-48">
                <InputText v-model="form.nombre" placeholder="Nombre del producto..." :invalid="enviado && !form.nombre.trim()" />
            </div>
            <InputNumber v-model="form.cantidad" placeholder="Cant" :min="1" :useGrouping="false" :invalid="enviado && !form.cantidad" inputClass="w-28" />
            <InputNumber v-model="form.precio" placeholder="$ Precio U." mode="currency" currency="MXN" locale="es-MX" :min="0" :invalid="enviado && !form.precio" inputClass="w-40" />
            <Button label="Registrar" icon="pi pi-plus" severity="success" class="ml-auto" @click="registrar" />
        </div>

        <DataTable
            :value="productos"
            dataKey="id"
            :paginator="true"
            :rows="5"
            :rowsPerPageOptions="[5, 10, 20]"
            paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
            currentPageReportTemplate="Visualizando {last} de {totalRecords} productos"
            showGridlines
        >
            <template #empty>No hay productos registrados.</template>
            <Column header="Cns" sortable sortField="id" style="width: 6rem">
                <template #body="{ index }">{{ index + 1 }}</template>
            </Column>
            <Column field="nombre" header="Nombre del Producto" sortable />
            <Column field="precio" header="Precio U." sortable style="width: 10rem">
                <template #body="{ data }">{{ moneda(data.precio) }}</template>
            </Column>
            <Column field="cantidad" header="Cant." sortable style="width: 6rem" bodyStyle="text-align: center" />
            <Column header="Precio P." style="width: 10rem">
                <template #body="{ data }">{{ moneda(data.precio * data.cantidad) }}</template>
            </Column>
            <Column header="Acciones" style="width: 8rem">
                <template #body="{ data }">
                    <div class="flex gap-1">
                        <Button icon="pi pi-pencil" severity="success" size="small" v-tooltip.top="'Editar'" @click="editar(data)" />
                        <Button icon="pi pi-trash" severity="danger" size="small" v-tooltip.top="'Eliminar'" @click="eliminar(data)" />
                    </div>
                </template>
            </Column>
        </DataTable>

        <div class="flex flex-wrap justify-end items-center gap-3 p-4 mt-4 border border-surface rounded-border">
            <label class="font-medium">Subtotal:</label>
            <InputText :value="moneda(subtotal)" readonly class="w-40" />
            <label class="font-medium">IVA (16%):</label>
            <InputText :value="moneda(iva)" readonly class="w-40" />
            <label class="font-semibold">Total:</label>
            <InputText :value="moneda(total)" readonly class="w-40 font-semibold" />
        </div>
    </div>

    <Dialog v-model:visible="dialogo" header="Editar producto" modal :style="{ width: '28rem' }">
        <div class="flex flex-col gap-4">
            <div class="flex flex-col gap-2">
                <label for="ed-nombre">Nombre del producto</label>
                <InputText id="ed-nombre" v-model="edicion.nombre" />
            </div>
            <div class="flex gap-3">
                <div class="flex flex-col gap-2 flex-1">
                    <label for="ed-cant">Cantidad</label>
                    <InputNumber inputId="ed-cant" v-model="edicion.cantidad" :min="1" :useGrouping="false" fluid />
                </div>
                <div class="flex flex-col gap-2 flex-1">
                    <label for="ed-precio">Precio U.</label>
                    <InputNumber inputId="ed-precio" v-model="edicion.precio" mode="currency" currency="MXN" locale="es-MX" :min="0" fluid />
                </div>
            </div>
        </div>
        <template #footer>
            <Button label="Cancelar" severity="secondary" outlined @click="dialogo = false" />
            <Button label="Guardar" icon="pi pi-check" @click="guardar" />
        </template>
    </Dialog>

    <ConfirmDialog />
</template>
