# Brief de diseño — Iconos de módulos (app offline SOVCA)

## 1. Contexto

Aplicación web instalable (PWA) para productores ganaderos. Se usa **en el campo**, con
un celular de gama media/baja, bajo luz solar directa y muchas veces sin señal. Por eso
los iconos deben leerse **de un vistazo y en pequeño**, sin detalles finos.

Los iconos acompañan el nombre de cada módulo en tres lugares de la interfaz:

1. Grilla "Módulos" de la pantalla de inicio (uso principal).
2. Cabecera dentro del módulo ya abierto.
3. Tira horizontal de pestañas.

Es el **mismo ícono** en los tres casos, solo cambia el tamaño.

---

## 2. Especificación técnica

### Formato de entrega

- **Principal: SVG** (obligatorio). Vectorial, editable, sin imágenes incrustadas ni texto convertido.
- **Opcional: PNG** de respaldo, 96 × 96 px, fondo transparente.

### Lienzo y medidas

| Concepto | Valor |
|---|---|
| Lienzo (área de dibujo) | **24 × 24** unidades |
| `viewBox` | `0 0 24 24` |
| Área útil de dibujo | **20 × 20**, centrada (margen de 2 unidades por lado) |
| Grosor de trazo | **1.7** unidades (rango aceptado: 1.5 – 1.8) |
| Terminaciones | `stroke-linecap="round"` y `stroke-linejoin="round"` |
| Tamaño en pantalla (inicio) | 26 px |
| Tamaño en pantalla (cabecera) | 22 px |
| Tamaño en pantalla (pestaña) | 20 px |
| Contenedor del inicio | 44 × 44 px, radio 12 px, fondo `#F1F7F0` |

> El ícono se ve a **26 px**, así que ningún detalle puede ser menor a ~1.5 unidades:
> se perdería. Menos elementos = mejor lectura.

### Color

- **Monocromo, un solo color**, siempre con `stroke="currentColor"`.
- El color final lo aplica el sistema: verde oscuro `#203923` en el inicio y en la cabecera,
  y **blanco puro** cuando la pestaña está activa. Por eso **no se admite** un color fijo
  dentro del archivo, ni degradados, ni sombras de color.
- Fondo: **transparente**. No incluir placa, recuadro ni esquinas redondeadas: el contenedor
  verde claro y el radio de 12 px ya los pone la aplicación.

### Estilo

- Trazo limpio, geométrico, esquinas redondeadas, estilo "línea".
- Sin rellenos sólidos (se permiten solo puntos mínimos, p. ej. los ojos de un animal).
- Sin texto, números, letras ni siglas dentro del ícono.
- Sin perspectiva, sin 3D, sin sombras paralelas, sin tramas ni medios tonos.
- Sin marcos, círculos de fondo ni líneas que toquen el borde del lienzo.
- **Peso visual uniforme** en los 9 iconos: que ninguno parezca más "gordo" ni más
  detallado que el resto.

### Nombre y organización de archivos

Un archivo por módulo, nombre en minúsculas y guion bajo, sin acentos ni espacios.
Carpeta contenedora: `modulos/`.

```
modulos/
├── animales.svg
├── nacimientos.svg
├── servicios.svg
├── control_sanitario.svg
├── produccion_leche.svg
├── pesos.svg
├── movimiento_rebano.svg
├── desincorporacion.svg
└── reportes.svg
```

---

## 3. Entregables

- [ ] 9 archivos SVG con la especificación anterior.
- [ ] (Opcional) 9 PNG de 96 × 96 px con fondo transparente, mismo nombre.
- [ ] Una lámina de contactos (PNG o PDF) con los 9 iconos a 26 px sobre fondo
      `#F1F7F0`, para validar la lectura real en tamaño de uso.
- [ ] Una vista previa a 26 px / 22 px / 20 px de cada icono, alineados entre sí,
      para comprobar el peso visual parejo.

---

## 4. Los 9 módulos

Cada ícono debe sugerir la **tarea del campo**, no el concepto abstracto.

| Archivo | Módulo | Qué representa / idea sugerida |
|---|---|---|
| `animales.svg` | Animales | Cabeza de bovino (orejas, ojos, hocico). Es el ícono "raíz" del sistema. |
| `nacimientos.svg` | Nacimientos | Madre con la cría al lado, o cría recién nacida. Debe distinguirse claramente de Animales. |
| `servicios.svg` | Servicios | Servicio reproductivo / gestación. Referencia sutil a reproducción, no a "servicio" genérico. |
| `control_sanitario.svg` | Control Sanitario | Frasco o vial de medicamento con cruz, o jeringa. Aplicación sanitaria. |
| `produccion_leche.svg` | Producción de Leche | Tarro o cántaro de leche con asa. |
| `pesos.svg` | Pesos | Balanza de platillos (no balanza digital ni báscula de baño). |
| `movimiento_rebano.svg` | Movimiento | Flechas opuestas o reja/potrero: traslado del rebaño entre potreros. |
| `desincorporacion.svg` | Desincorporaciones | Ficha o caravana con una X: el animal sale del hato. No usar un tacho de basura. |
| `reportes.svg` | Reportes | Barras o gráfico simple sobre una línea de base. |

> Los conceptos son una guía. Se aceptan propuestas alternativas siempre que los 9 iconos
> formen un **conjunto coherente y distinguible entre sí a 26 px**.

---

## 5. Errores a evitar

- Íconos con proporciones distintas entre sí (unos anchos, otros verticales): todos deben
  ocupar el área útil de forma equilibrada.
- Trazo demasiado fino (bajo 1.5 unidades): desaparece en pantallas con sol.
- Detalle excesivo (pelos, manchas, texturas): se convierte en una mancha a 26 px.
- Rellenos con color fijo: impiden el cambio a blanco en la pestaña activa.
- Elementos que tocan el borde del lienzo: el ícono se ve "encajonado".
