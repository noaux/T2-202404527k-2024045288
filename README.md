# Tarea 2: Plataforma Clínica SaludUSM (PHP + MySQL)

## 1. Identificación del Equipo
* **Diego Abarca** - Rol: 202404528-8
* **Joaquin Venegas** - Rol: 202404527-k

**Enlace del Repositorio (GitHub):** 
[https://github.com/noaux/T2-202404527k-2024045288.git](https://github.com/noaux/T2-202404527k-2024045288.git)

---

## 2. Instrucciones de Ejecución
Para hacer funcionar correctamente nuestro proyecto trabajamos con XAMMP (MariaDB), porfavor siga estos pasos:

1. **Preparar la Base de Datos:**
   * Abra su gestor de MySQL.
   * Cree una base de datos nueva llamada `salud_usm` (con cotejamiento utf8mb4 preferentemente).
   * Importe el archivo `base.sql` proporcionado en la carpeta `BD`. 
   * *Nota:* El script está diseñado para ejecutarse limpiamente. Borrará tablas existentes, creará la estructura nueva y ejecutará automáticamente un procedimiento almacenado (`PoblarDatosDinamicos`) que generará los pacientes, citas médicas, diagnósticos y recetas de prueba de forma aleatoria, además de los datos estáticos obligatorios.

2. **Preparar el Servidor Web:**
   * Mueva la carpeta con los archivos `.php` junto con la imagen `.jpg` al directorio raíz de su servidor web.
   * Verifique que en el archivo `conexion.php` las credenciales correspondan a las de su servidor local (por defecto `root` sin contraseña).

3. **Ejecutar la Plataforma:**
   * Abra su navegador web e ingrese a la ruta del proyecto: `http://localhost/T2-2024045288-202404527K/login.php`.

---

## 3. Credenciales de Acceso (Usuarios de Prueba)
El script de base de datos genera usuarios listos para probar los tres roles del sistema. A continuación, los accesos principales:

* **ROL 3 - Administrador de SaludUSM**
  * **Email:** `admin@saludusm.cl`
  * **Contraseña:** `Admin123!`

* **ROL 2 - Médico (Ejemplo: Dr. Juan Silva)**
  * **Email:** `jsilva@clinica.cl`
  * **Contraseña:** `Medico123!`
  * *(El sistema cuenta con 15 médicos de prueba en total, todos comparten la misma contraseña `Medico123!`).*

* **ROL 1 - Paciente**
  * **Email:** `paciente1@correo.cl` (Puede usar desde paciente1 hasta `paciente40@correo.cl`)
  * **Contraseña:** `Paciente123!`

---

## 4. Definiciones y Reglas de Negocio (Backend)

* **Requisitos de Contraseña (Registro):** 
  Para registrar a un nuevo paciente, la contraseña debe cumplir estrictamente con los siguientes requisitos definidos en el sistema:
  1. Longitud mínima de **8 caracteres**.
  2. Al menos **una letra mayúscula**.
  3. Al menos **un símbolo** (carácter especial no alfanumérico).

* **Horarios de Atención y Duración de Bloques:**
  * **Horario de atención:** De 09:00 a 17:00 hrs.
  * **Duración del bloque:** Cada cita tiene una duración exacta de **1 hora** (bloques cerrados: 09:00, 10:00, 11:00, etc.).
  * **Disponibilidad:** El algoritmo de agendamiento (`agendar_cita.php`) excluye automáticamente los fines de semana (sábados y domingos) y proyecta los horarios disponibles del médico para los próximos **7 días hábiles**.

---

## 5. Manejo de Eventos y Decisiones de Diseño

* **Eliminación de Cuenta (Paciente):** 
  Si un paciente decide usar la "Zona de Peligro" para eliminar su cuenta, el sistema ejecuta un `DELETE` físico sobre su ID en la tabla `usuario`. Gracias a la restricción `ON DELETE CASCADE` de la base de datos, esta acción es irreversible y **destruye automáticamente el registro del paciente en la tabla `paciente`, así como TODAS sus citas asociadas** (tanto el historial médico pasado como las reservas futuras que tuviera agendadas), atenciones, diagnósticos y recetas.

* **Actualización de Citas Vencidas (Reservadas/Confirmadas a Canceladas):**
  Esta actualización se ejecuta de manera silenciosa en el archivo `login.php`. Se decidió colocarla allí para que **cada vez que cualquier usuario cargue la pantalla de inicio de sesión**, el sistema limpie automáticamente la base de datos cambiando a estado `Cancelada` (5) todas las citas en estado `Reservada` (1) o `Confirmada` (2) cuya fecha y hora (`fecha_hora`) ya sean anteriores al momento actual (`NOW()`). De esta forma, el sistema siempre está depurado antes de que alguien ingrese.

* **Edición de Médicos (Quitar Especialidad o Centro):**
  Si el Administrador intenta desvincular una especialidad o un centro médico del perfil de un doctor, el sistema (`gestion_medicos.php`) realiza comprobaciones lógicas preventivas:
  1. **Validación de citas futuras:** Si el médico tiene citas futuras vigentes (estado Reservada o Confirmada) asociadas a *esa especialidad* o en *ese centro*, la plataforma **bloquea** la acción, arrojando una alerta roja que indica que no se puede desvincular hasta que dichas citas sean atendidas o canceladas.
  2. **Validación de mínimos:** También se impide la acción si la desvinculación dejara al médico con 0 especialidades o 0 centros de atención (haciendo cumplir la regla de mínimo 1).

---

## 6. Supuestos Asumidos

1. **Jornada Laboral y Agendamiento:** Se asumió que los recintos médicos solo operan de lunes a viernes y que las citas se atienden siempre en el minuto 00 (horas en punto).
2. **Generación de Datos de Prueba:** Se asumió que el uso de un Procedimiento Almacenado de MySQL (`PoblarDatosDinamicos`) era la mejor vía para cumplir con la cuota de poblamiento solicitada (40 pacientes, 150 citas, recetas, etc.) permitiendo a su vez evaluar el script limpiamente sin cargar archivos SQL pesados.
3. **Zonas Horarias:** Se asumió en todo el código PHP la zona horaria `America/Santiago` mediante `date_default_timezone_set()` para evitar desfases con la ejecución de citas vencidas.
4. **Roles del Sistema:** Se asumió que solo los pacientes requieren auto-registro vía web. Las cuentas de los médicos son generadas internamente por el Administrador a través del panel de gestión.
