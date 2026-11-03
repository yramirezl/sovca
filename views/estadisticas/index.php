<?php

/**
 * =====================================================================
 * views/estadisticas/index.php — ESTADÍSTICAS DEL SISTEMA (admin)
 * ---------------------------------------------------------------------
 * Punto de entrada del submenú "Estadísticas" del administrador:
 *
 *   · Registros por Fundos  → views/dataxfundos/
 *   · Conexiones            → views/conexiones/
 *
 * Existe porque el acceso rápido "Estadísticas" del inicio abría
 * ../dashboard/dashboard, que es el tablero de UN fundo (exclusivo del
 * productor): sin fundo activo el administrador lo veía vacío. Aquí se
 * ofrecen las dos vistas que sí son del administrador, igual que en el
 * menú lateral.
 * =====================================================================
 */

include("../../lib/validar_session.php");
ValidaSession("../login");
VerificarAdmin($_SESSION['rolx']);

?>
<?php include("../layouts/constantes.php"); ?>
<?php include_once("../layouts/cabecera.php"); ?>

<style>
/* ============================================================
   ESTADÍSTICAS · entrada del submenú del administrador
   ============================================================ */
.est-wrap { padding: 4px 2px 28px; }

/* ---------- Encabezado ---------- */
.est-hero {
    display: flex; align-items: center; gap: 14px; flex-wrap: wrap;
    background: #fff; border: 1px solid #e5e8eb; border-radius: 14px;
    padding: 14px 18px; margin-bottom: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,.04);
}
.est-hero__icon {
    width: 44px; height: 44px; border-radius: 12px; flex: 0 0 44px;
    display: flex; align-items: center; justify-content: center;
    background: #eaf6ee; color: #2f8f4a; font-size: 20px;
}
.est-hero__text { flex: 1 1 320px; min-width: 0; }
.est-hero__text h1 { margin: 0; font-size: 19px; font-weight: 800; color: #1b3a24; }
.est-hero__text p  { margin: 2px 0 0; font-size: 13px; color: #6b7785; }

/* ---------- Tarjetas ---------- */
.est-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 14px;
}
.est-card {
    display: flex; align-items: flex-start; gap: 14px;
    background: #fff; border: 1px solid #e5e8eb; border-left: 4px solid #2f8f4a;
    border-radius: 14px; padding: 18px;
    text-decoration: none; color: inherit;
    box-shadow: 0 1px 3px rgba(0,0,0,.04);
    transition: transform .16s ease, box-shadow .16s ease, border-color .16s ease;
}
.est-card:hover,
.est-card:focus {
    text-decoration: none; color: inherit;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(27, 58, 36, .12);
    border-color: #cfe3d6;
}
.est-card__icon {
    width: 46px; height: 46px; border-radius: 12px; flex: 0 0 46px;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px; background: #eaf6ee; color: #2f8f4a;
}
.est-card__icon--info { background: #e8f1fb; color: #2b7bbb; }
.est-card__txt { flex: 1 1 auto; min-width: 0; }
.est-card__txt strong {
    display: block; font-size: 15px; font-weight: 800; color: #1b3a24;
    margin-bottom: 3px;
}
.est-card__txt small {
    display: block; font-size: 12.5px; line-height: 1.5; color: #6b7785;
}
.est-card__go {
    flex: 0 0 auto; align-self: center; color: #b6c1ca; font-size: 16px;
    transition: color .16s ease, transform .16s ease;
}
.est-card:hover .est-card__go { color: #2f8f4a; transform: translateX(3px); }

/* ---------- Nota al pie ---------- */
.est-nota {
    margin: 16px 0 0; font-size: 12.5px; color: #7b8794;
    display: flex; align-items: flex-start; gap: 8px;
}
.est-nota i { color: #2f8f4a; margin-top: 2px; }
.est-nota a { font-weight: 600; }

@media (max-width: 575px) {
    .est-card { padding: 15px; gap: 12px; }
    .est-card__icon { width: 40px; height: 40px; flex-basis: 40px; font-size: 17px; }
}
</style>

<div class="est-wrap">

    <!-- ================= Encabezado ================= -->
    <div class="est-hero">
        <div class="est-hero__icon"><i class="fa fa-bar-chart"></i></div>
        <div class="est-hero__text">
            <h1>Estadísticas</h1>
            <p>Indicadores de todo el sistema: registro por fundo y uso del mismo.</p>
        </div>
    </div>

    <!-- ================= Opciones del submenú ================= -->
    <div class="est-grid">

        <a class="est-card" href="../dataxfundos/">
            <span class="est-card__icon est-card__icon--info"><i class="fa fa-database"></i></span>
            <span class="est-card__txt">
                <strong>Registros por Fundos</strong>
                <small>Animales, servicios, nacimientos, controles, producción y bajas de cada fundo, consolidados en una sola tabla.</small>
            </span>
            <span class="est-card__go"><i class="fa fa-arrow-right"></i></span>
        </a>

        <a class="est-card" href="../conexiones/">
            <span class="est-card__icon"><i class="fa fa-plug"></i></span>
            <span class="est-card__txt">
                <strong>Conexiones</strong>
                <small>Quién entra al sistema, desde dónde, con qué frecuencia y cuánto tiempo permanece conectado.</small>
            </span>
            <span class="est-card__go"><i class="fa fa-arrow-right"></i></span>
        </a>

    </div>

    <p class="est-nota">
        <i class="fa fa-info-circle" aria-hidden="true"></i>
        <span>Los tableros con indicadores de un fundo concreto (animales, gestantes, servidas…) se ven
            dentro del fundo: entra con <a href="../fundos/?modo=soporte">Modo soporte</a>.</span>
    </p>

</div>

<?php include_once("../layouts/pie.php"); ?>
