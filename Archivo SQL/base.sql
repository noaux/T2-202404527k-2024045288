-- ==========================================================
-- 1. LIMPIEZA INICIAL (Hace que el script se pueda re-ejecutar sin errores)
-- ==========================================================
SET FOREIGN_KEY_CHECKS = 0;

-- Borrar tablas
DROP TABLE IF EXISTS atencion_diagnostico, medico_especialidad, medico_centro, receta, atencion, cita, medico, paciente, diagnostico, centro_medico, estado_cita, prevision, especialidad, usuario, rol;

-- Borrar objetos de lógica de negocio (Vistas, Procedimientos y Funciones)
DROP VIEW IF EXISTS vista_resumen_citas;
DROP PROCEDURE IF EXISTS sp_estadisticas_administrador;
DROP FUNCTION IF EXISTS fn_puede_agregar_especialidad;

SET FOREIGN_KEY_CHECKS = 1;

-- ==========================================================
-- 2. CREACIÓN DE TABLAS DE SEGURIDAD (Login)
-- ==========================================================
CREATE TABLE rol (
    id_rol INT AUTO_INCREMENT PRIMARY KEY,
    nombre_rol VARCHAR(50) UNIQUE NOT NULL
);

CREATE TABLE usuario (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    id_rol INT NOT NULL,
    FOREIGN KEY (id_rol) REFERENCES rol(id_rol) ON DELETE RESTRICT
);

-- ==========================================================
-- 3. CREACIÓN DE TABLAS DE DOMINIO (Catálogos)
-- ==========================================================
CREATE TABLE especialidad (
    id_especialidad INT AUTO_INCREMENT PRIMARY KEY,
    nom_especialidad VARCHAR(50) UNIQUE NOT NULL
);

CREATE TABLE prevision (
    id_prevision INT AUTO_INCREMENT PRIMARY KEY,
    nom_prevision VARCHAR(50) UNIQUE NOT NULL
);

CREATE TABLE estado_cita (
    id_estado INT AUTO_INCREMENT PRIMARY KEY,
    nom_estado VARCHAR(50) UNIQUE NOT NULL
);

CREATE TABLE centro_medico (
    codigo_interno VARCHAR(10) PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    region VARCHAR(50) NOT NULL,
    comuna VARCHAR(50) NOT NULL
);

CREATE TABLE diagnostico (
    codigo_cie10 VARCHAR(10) PRIMARY KEY,
    descripcion VARCHAR(255) NOT NULL
);

-- ==========================================================
-- 4. CREACIÓN DE TABLAS MAESTRAS (Ya conectadas con Usuarios)
-- ==========================================================
CREATE TABLE paciente (
    rut_paciente VARCHAR(12) PRIMARY KEY,
    nom_completo VARCHAR(100) NOT NULL,
    fecha_nacimiento DATE NOT NULL,
    sexo CHAR(1) NOT NULL,
    telefono VARCHAR(15),
    comuna VARCHAR(50) NOT NULL,
    id_prevision INT NOT NULL,
    id_usuario INT UNIQUE NOT NULL,
    FOREIGN KEY (id_prevision) REFERENCES prevision(id_prevision) ON DELETE RESTRICT,
    FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE
);

CREATE TABLE medico (
    rut_medico VARCHAR(12) PRIMARY KEY,
    nom_completo VARCHAR(100) NOT NULL,
    email_institucional VARCHAR(100) UNIQUE NOT NULL,
    id_usuario INT UNIQUE NOT NULL,
    FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE
);

-- ==========================================================
-- 5. CREACIÓN DE TABLAS TRANSACCIONALES Y RELACIONES
-- ==========================================================
CREATE TABLE cita (
    id_cita INT AUTO_INCREMENT PRIMARY KEY,
    fecha_hora DATETIME NOT NULL, 
    id_estado INT NOT NULL,
    rut_paciente VARCHAR(12) NOT NULL,
    rut_medico VARCHAR(12) NOT NULL,
    codigo_interno VARCHAR(10) NOT NULL,
    id_especialidad INT NOT NULL,
    FOREIGN KEY (id_estado) REFERENCES estado_cita(id_estado) ON DELETE RESTRICT,
    FOREIGN KEY (rut_paciente) REFERENCES paciente(rut_paciente) ON DELETE CASCADE,
    FOREIGN KEY (rut_medico) REFERENCES medico(rut_medico) ON DELETE CASCADE,
    FOREIGN KEY (codigo_interno) REFERENCES centro_medico(codigo_interno) ON DELETE CASCADE,
    FOREIGN KEY (id_especialidad) REFERENCES especialidad(id_especialidad) ON DELETE CASCADE
);

CREATE TABLE atencion (
    id_atencion INT AUTO_INCREMENT PRIMARY KEY,
    motivo_consulta TEXT NOT NULL,
    observaciones TEXT,
    id_cita INT UNIQUE NOT NULL,
    FOREIGN KEY (id_cita) REFERENCES cita(id_cita) ON DELETE CASCADE
);

CREATE TABLE receta (
    id_receta INT AUTO_INCREMENT PRIMARY KEY,
    medicamento VARCHAR(255) NOT NULL,
    dosis VARCHAR(100) NOT NULL,
    dias_tratamiento INT NOT NULL,
    id_atencion INT NOT NULL,
    FOREIGN KEY (id_atencion) REFERENCES atencion(id_atencion) ON DELETE CASCADE
);

CREATE TABLE medico_centro (
    rut_medico VARCHAR(12),
    codigo_interno VARCHAR(10),
    PRIMARY KEY (rut_medico, codigo_interno),
    FOREIGN KEY (rut_medico) REFERENCES medico(rut_medico) ON DELETE CASCADE,
    FOREIGN KEY (codigo_interno) REFERENCES centro_medico(codigo_interno) ON DELETE CASCADE
);

CREATE TABLE medico_especialidad (
    rut_medico VARCHAR(12),
    id_especialidad INT,
    PRIMARY KEY (rut_medico, id_especialidad),
    FOREIGN KEY (rut_medico) REFERENCES medico(rut_medico) ON DELETE CASCADE,
    FOREIGN KEY (id_especialidad) REFERENCES especialidad(id_especialidad) ON DELETE CASCADE
);

CREATE TABLE atencion_diagnostico (
    id_atencion INT,
    codigo_cie10 VARCHAR(10),
    PRIMARY KEY (id_atencion, codigo_cie10),
    FOREIGN KEY (id_atencion) REFERENCES atencion(id_atencion) ON DELETE CASCADE,
    FOREIGN KEY (codigo_cie10) REFERENCES diagnostico(codigo_cie10) ON DELETE CASCADE
);

-- ==========================================================
-- 6. POBLAMIENTO DE DATOS ESTÁTICOS Y USUARIOS BASE
-- ==========================================================
INSERT INTO rol (nombre_rol) VALUES ('Paciente'), ('Médico'), ('Administrador');

-- Usuario Administrador por defecto
INSERT INTO usuario (email, password, id_rol) VALUES ('admin@saludusm.cl', 'Admin123!', 3);

INSERT INTO especialidad (nom_especialidad) VALUES
('Medicina General'), ('Cardiología'), ('Pediatría'), ('Traumatología'), ('Dermatología'), ('Neurología'), ('Psiquiatría');

INSERT INTO centro_medico (codigo_interno, nombre, region, comuna) VALUES
('CM1', 'Centro Médico Santiago', 'Metropolitana', 'Santiago'),
('CM2', 'Clínica Sur', 'Metropolitana', 'San Miguel'),
('CM3', 'Centro Salud Providencia', 'Metropolitana', 'Providencia'),
('CM4', 'Hospital Florida', 'Metropolitana', 'La Florida'),
('CM5', 'Clínica Regional Valparaíso', 'Valparaíso', 'Viña del Mar');

INSERT INTO diagnostico (codigo_cie10, descripcion) VALUES
('J00', 'Resfriado común'), ('E11', 'Diabetes tipo 2'), ('I10', 'Hipertensión'), ('J45', 'Asma'), ('M54', 'Lumbago'),
('A09', 'Gastroenteritis'), ('J03', 'Amigdalitis aguda'), ('H10', 'Conjuntivitis'), ('R51', 'Cefalea'), ('L20', 'Dermatitis atópica');

INSERT INTO prevision (nom_prevision) VALUES ('Fonasa'), ('Isapre'), ('Particular');
INSERT INTO estado_cita (nom_estado) VALUES ('Reservada'), ('Confirmada'), ('Atendida'), ('No Asistió'), ('Cancelada');

-- Creación de 15 Médicos con sus respectivas cuentas de Usuario (Rol 2)
INSERT INTO usuario (email, password, id_rol) VALUES ('jsilva@clinica.cl', 'Medico123!', 2);
INSERT INTO medico (rut_medico, nom_completo, email_institucional, id_usuario) VALUES ('10234567-8', 'Dr. Juan Silva', 'jsilva@clinica.cl', LAST_INSERT_ID());

INSERT INTO usuario (email, password, id_rol) VALUES ('avega@clinica.cl', 'Medico123!', 2);
INSERT INTO medico (rut_medico, nom_completo, email_institucional, id_usuario) VALUES ('10876543-2', 'Dra. Ana Vega', 'avega@clinica.cl', LAST_INSERT_ID());

INSERT INTO usuario (email, password, id_rol) VALUES ('lruiz@clinica.cl', 'Medico123!', 2);
INSERT INTO medico (rut_medico, nom_completo, email_institucional, id_usuario) VALUES ('11456789-0', 'Dr. Luis Ruiz', 'lruiz@clinica.cl', LAST_INSERT_ID());

INSERT INTO usuario (email, password, id_rol) VALUES ('psoto@clinica.cl', 'Medico123!', 2);
INSERT INTO medico (rut_medico, nom_completo, email_institucional, id_usuario) VALUES ('12123456-7', 'Dra. Paz Soto', 'psoto@clinica.cl', LAST_INSERT_ID());

INSERT INTO usuario (email, password, id_rol) VALUES ('dlara@clinica.cl', 'Medico123!', 2);
INSERT INTO medico (rut_medico, nom_completo, email_institucional, id_usuario) VALUES ('12987654-3', 'Dr. Diego Lara', 'dlara@clinica.cl', LAST_INSERT_ID());

INSERT INTO usuario (email, password, id_rol) VALUES ('rmella@clinica.cl', 'Medico123!', 2);
INSERT INTO medico (rut_medico, nom_completo, email_institucional, id_usuario) VALUES ('13555666-4', 'Dra. Rosa Mella', 'rmella@clinica.cl', LAST_INSERT_ID());

INSERT INTO usuario (email, password, id_rol) VALUES ('ppino@clinica.cl', 'Medico123!', 2);
INSERT INTO medico (rut_medico, nom_completo, email_institucional, id_usuario) VALUES ('14111222-3', 'Dr. Pedro Pino', 'ppino@clinica.cl', LAST_INSERT_ID());

INSERT INTO usuario (email, password, id_rol) VALUES ('sluna@clinica.cl', 'Medico123!', 2);
INSERT INTO medico (rut_medico, nom_completo, email_institucional, id_usuario) VALUES ('14777888-5', 'Dra. Sara Luna', 'sluna@clinica.cl', LAST_INSERT_ID());

INSERT INTO usuario (email, password, id_rol) VALUES ('hvera@clinica.cl', 'Medico123!', 2);
INSERT INTO medico (rut_medico, nom_completo, email_institucional, id_usuario) VALUES ('15222333-K', 'Dr. Hugo Vera', 'hvera@clinica.cl', LAST_INSERT_ID());

INSERT INTO usuario (email, password, id_rol) VALUES ('itoro@clinica.cl', 'Medico123!', 2);
INSERT INTO medico (rut_medico, nom_completo, email_institucional, id_usuario) VALUES ('15999000-1', 'Dra. Ines Toro', 'itoro@clinica.cl', LAST_INSERT_ID());

INSERT INTO usuario (email, password, id_rol) VALUES ('ocaro@clinica.cl', 'Medico123!', 2);
INSERT INTO medico (rut_medico, nom_completo, email_institucional, id_usuario) VALUES ('16444555-6', 'Dr. Omar Caro', 'ocaro@clinica.cl', LAST_INSERT_ID());

INSERT INTO usuario (email, password, id_rol) VALUES ('emora@clinica.cl', 'Medico123!', 2);
INSERT INTO medico (rut_medico, nom_completo, email_institucional, id_usuario) VALUES ('16888999-7', 'Dra. Ema Mora', 'emora@clinica.cl', LAST_INSERT_ID());

INSERT INTO usuario (email, password, id_rol) VALUES ('iroa@clinica.cl', 'Medico123!', 2);
INSERT INTO medico (rut_medico, nom_completo, email_institucional, id_usuario) VALUES ('17333444-8', 'Dr. Ivan Roa', 'iroa@clinica.cl', LAST_INSERT_ID());

INSERT INTO usuario (email, password, id_rol) VALUES ('lpena@clinica.cl', 'Medico123!', 2);
INSERT INTO medico (rut_medico, nom_completo, email_institucional, id_usuario) VALUES ('17888999-9', 'Dra. Luz Pena', 'lpena@clinica.cl', LAST_INSERT_ID());

INSERT INTO usuario (email, password, id_rol) VALUES ('arey@clinica.cl', 'Medico123!', 2);
INSERT INTO medico (rut_medico, nom_completo, email_institucional, id_usuario) VALUES ('18123123-0', 'Dr. Alex Rey', 'arey@clinica.cl', LAST_INSERT_ID());

-- Asignar especialidades y centros al azar a los médicos
INSERT INTO medico_especialidad (rut_medico, id_especialidad) SELECT rut_medico, FLOOR(1 + (RAND() * 7)) FROM medico;
INSERT IGNORE INTO medico_especialidad (rut_medico, id_especialidad) SELECT rut_medico, FLOOR(1 + (RAND() * 7)) FROM medico WHERE RAND() > 0.5;

INSERT INTO medico_centro (rut_medico, codigo_interno) SELECT rut_medico, CONCAT('CM', FLOOR(1 + (RAND() * 5))) FROM medico;
INSERT IGNORE INTO medico_centro (rut_medico, codigo_interno) SELECT rut_medico, CONCAT('CM', FLOOR(1 + (RAND() * 5))) FROM medico WHERE RAND() > 0.5;

-- ==========================================================
-- 7. POBLAMIENTO DE DATOS DINÁMICOS Y LÓGICOS (SP)
-- ==========================================================
DELIMITER //
CREATE PROCEDURE PoblarDatosDinamicos()
BEGIN
    DECLARE i INT DEFAULT 1;
    DECLARE v_rut VARCHAR(12);
    DECLARE v_rut_med VARCHAR(12);
    DECLARE v_id_esp INT;
    DECLARE v_cod_c VARCHAR(10);
    DECLARE v_estado INT;
    DECLARE v_rand INT;
    DECLARE v_id_cita INT;
    DECLARE v_id_aten INT;
    DECLARE v_id_user INT;
    
    -- Variables para la lógica médica
    DECLARE v_diag VARCHAR(10);
    DECLARE v_med VARCHAR(255);
    DECLARE v_dosis VARCHAR(100);

    -- Variables para sincronizar género y nombre
    DECLARE v_sexo CHAR(1);
    DECLARE v_nombre VARCHAR(50);

    -- Generar 40 Pacientes con sus cuentas de Usuario (Rol 1)
    WHILE i <= 40 DO
        INSERT INTO usuario (email, password, id_rol) 
        VALUES (CONCAT('paciente', i, '@correo.cl'), 'Paciente123!', 1);
        
        SET v_id_user = LAST_INSERT_ID();

        -- Sincronización lógica de datos personales
        IF RAND() > 0.5 THEN
            SET v_sexo = 'F';
            SET v_nombre = ELT(FLOOR(1 + (RAND() * 5)), 'Maria', 'Camila', 'Valentina', 'Sofia', 'Constanza');
        ELSE
            SET v_sexo = 'M';
            SET v_nombre = ELT(FLOOR(1 + (RAND() * 5)), 'Juan', 'Pedro', 'Jose', 'Diego', 'Matias');
        END IF;

        INSERT INTO paciente (rut_paciente, nom_completo, fecha_nacimiento, sexo, telefono, comuna, id_prevision, id_usuario)
        VALUES (
            CONCAT(FLOOR(10000000 + (RAND() * 15000000)), '-', ELT(FLOOR(1 + (RAND() * 11)), '0','1','2','3','4','5','6','7','8','9','K')), 
            CONCAT(v_nombre, ' ', ELT(FLOOR(1 + (RAND() * 8)), 'Gonzalez', 'Muñoz', 'Rojas', 'Diaz', 'Perez', 'Soto', 'Contreras', 'Silva')),
            DATE_SUB(CURDATE(), INTERVAL FLOOR(20 + (RAND() * 60)) YEAR),
            v_sexo,
            CONCAT('9876543', FLOOR(RAND() * 10)),
            ELT(FLOOR(1 + (RAND() * 8)), 'Santiago', 'Ñuñoa', 'Providencia', 'Viña del Mar', 'Quilpué', 'Maipú', 'La Florida', 'Valparaíso'),
            FLOOR(1 + (RAND() * 3)),
            v_id_user
        );
        SET i = i + 1;
    END WHILE;

    -- Generar 150 Citas Cruzadas
    SET i = 1;
    WHILE i <= 150 DO
        SELECT rut_paciente INTO v_rut FROM paciente ORDER BY RAND() LIMIT 1;
        SELECT rut_medico INTO v_rut_med FROM medico ORDER BY RAND() LIMIT 1;
        
        SELECT id_especialidad INTO v_id_esp FROM medico_especialidad WHERE rut_medico = v_rut_med ORDER BY RAND() LIMIT 1;
        SELECT codigo_interno INTO v_cod_c FROM medico_centro WHERE rut_medico = v_rut_med ORDER BY RAND() LIMIT 1;
        
        SET v_rand = FLOOR(1 + (RAND() * 100));
        IF v_rand <= 25 THEN SET v_estado = 4;
        ELSEIF v_rand <= 35 THEN SET v_estado = 5;
        ELSEIF v_rand <= 50 THEN SET v_estado = 1;
        ELSE SET v_estado = 3;
        END IF;

        INSERT INTO cita (fecha_hora, id_estado, rut_paciente, rut_medico, codigo_interno, id_especialidad)
        VALUES (DATE_ADD('2026-10-01 10:00:00', INTERVAL i HOUR), v_estado, v_rut, v_rut_med, v_cod_c, v_id_esp);
        
        SET v_id_cita = LAST_INSERT_ID();

        IF v_estado = 3 THEN 
            INSERT INTO atencion (motivo_consulta, observaciones, id_cita)
            VALUES ('Consulta de control', 'Paciente asiste a evaluación', v_id_cita);
            
            SET v_id_aten = LAST_INSERT_ID();
            
            -- LÓGICA MÉDICA
            SET v_diag = ELT(FLOOR(1 + (RAND() * 10)), 'J00', 'E11', 'I10', 'J45', 'M54', 'A09', 'J03', 'H10', 'R51', 'L20');
            INSERT INTO atencion_diagnostico (id_atencion, codigo_cie10) VALUES (v_id_aten, v_diag);

            IF v_diag = 'J00' THEN SET v_med = 'Paracetamol'; SET v_dosis = '1 comprimido c/8 hrs';
            ELSEIF v_diag = 'E11' THEN SET v_med = 'Metformina'; SET v_dosis = '1 comprimido al día';
            ELSEIF v_diag = 'I10' THEN SET v_med = 'Losartán'; SET v_dosis = '1 comprimido al día';
            ELSEIF v_diag = 'J45' THEN SET v_med = 'Salbutamol'; SET v_dosis = '2 puff c/6 hrs';
            ELSEIF v_diag = 'M54' THEN SET v_med = 'Ketorolaco'; SET v_dosis = '1 comprimido c/8 hrs';
            ELSEIF v_diag = 'A09' THEN SET v_med = 'Viadil'; SET v_dosis = '20 gotas c/8 hrs';
            ELSEIF v_diag = 'J03' THEN SET v_med = 'Amoxicilina'; SET v_dosis = '500mg c/8 hrs';
            ELSEIF v_diag = 'H10' THEN SET v_med = 'Gotas Oftálmicas'; SET v_dosis = '1 gota cada ojo c/8 hrs';
            ELSEIF v_diag = 'R51' THEN SET v_med = 'Ibuprofeno'; SET v_dosis = '600mg en caso de dolor';
            ELSEIF v_diag = 'L20' THEN SET v_med = 'Crema Hidrocortisona'; SET v_dosis = 'Aplicar 2 veces al día';
            END IF;

            INSERT INTO receta (medicamento, dosis, dias_tratamiento, id_atencion)
            VALUES (v_med, v_dosis, FLOOR(3 + (RAND() * 7)), v_id_aten);
        END IF;
        
        SET i = i + 1;
    END WHILE;

    -- Parche de Pediatría
    SELECT a.id_atencion INTO v_id_aten FROM atencion a JOIN cita c ON a.id_cita = c.id_cita WHERE c.id_especialidad = 3 AND c.id_estado = 3 LIMIT 1;
    IF v_id_aten IS NOT NULL THEN
        INSERT INTO receta (medicamento, dosis, dias_tratamiento, id_atencion) VALUES
        ('Amoxicilina', '5 ml c/8 hrs', 7, v_id_aten),
        ('Salbutamol', '2 puff c/6 hrs', 5, v_id_aten);
    END IF;

END //
DELIMITER ;

CALL PoblarDatosDinamicos();
DROP PROCEDURE PoblarDatosDinamicos;

-- ==========================================================
-- 8. OBJETOS SQL AVANZADOS (TAREA 2)
-- ==========================================================

-- A. PROCEDIMIENTO ALMACENADO (Panel de Gestión - Administrador)
-- Calcula estadísticas por centro y el top 5 de diagnósticos.
DELIMITER //

CREATE PROCEDURE sp_estadisticas_administrador()
BEGIN
    -- Tabla 1: Estadísticas por Centro Médico
    SELECT 
        cm.nombre AS centro_medico,
        COUNT(c.id_cita) AS total_citas,
        -- Calculamos el %: (Citas donde estado es 4 / Total de citas) * 100. Redondeado a 2 decimales.
        ROUND(IFNULL(SUM(CASE WHEN c.id_estado = 4 THEN 1 ELSE 0 END) / COUNT(c.id_cita) * 100, 0), 2) AS porcentaje_inasistencia
    FROM 
        centro_medico cm
    LEFT JOIN 
        cita c ON cm.codigo_interno = c.codigo_interno
    GROUP BY 
        cm.codigo_interno, cm.nombre;

    -- Tabla 2: Los 5 diagnósticos más frecuentes de la red
    SELECT 
        d.descripcion AS diagnostico,
        COUNT(ad.id_atencion) AS cantidad_casos
    FROM 
        diagnostico d
    JOIN 
        atencion_diagnostico ad ON d.codigo_cie10 = ad.codigo_cie10
    GROUP BY 
        d.codigo_cie10, d.descripcion
    ORDER BY 
        cantidad_casos DESC
    LIMIT 5;
END //

DELIMITER ;

-- B. TRIGGERS (Reglas de Negocio - CRUD Citas)
-- Bloquean automáticamente el ingreso o modificación si hay choque de horarios para el médico o el paciente.
DELIMITER //

-- Trigger 1: Al CREAR una cita nueva
CREATE TRIGGER trg_evitar_choque_citas_insert
BEFORE INSERT ON cita
FOR EACH ROW
BEGIN
    DECLARE v_choque_medico INT;
    DECLARE v_choque_paciente INT;

    -- 1. Validar que el médico no tenga otra cita activa (Reservada, Confirmada o Atendida) a esa misma hora
    SELECT COUNT(*) INTO v_choque_medico FROM cita
    WHERE rut_medico = NEW.rut_medico 
      AND fecha_hora = NEW.fecha_hora 
      AND id_estado IN (1, 2, 3);

    IF v_choque_medico > 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Error de Negocio: El médico ya tiene una cita agendada en esa fecha y hora exacta.';
    END IF;

    -- 2. Validar que el paciente no tenga otra cita activa a esa misma hora
    SELECT COUNT(*) INTO v_choque_paciente FROM cita
    WHERE rut_paciente = NEW.rut_paciente 
      AND fecha_hora = NEW.fecha_hora 
      AND id_estado IN (1, 2, 3);

    IF v_choque_paciente > 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Error de Negocio: El paciente ya tiene otra cita agendada en esa fecha y hora exacta.';
    END IF;
END //

-- Trigger 2: Al REPROGRAMAR una cita existente
CREATE TRIGGER trg_evitar_choque_citas_update
BEFORE UPDATE ON cita
FOR EACH ROW
BEGIN
    DECLARE v_choque_medico INT;
    DECLARE v_choque_paciente INT;

    -- Solo validar si el usuario está intentando cambiar la fecha/hora original
    IF NEW.fecha_hora != OLD.fecha_hora THEN
        
        SELECT COUNT(*) INTO v_choque_medico FROM cita
        WHERE rut_medico = NEW.rut_medico 
          AND fecha_hora = NEW.fecha_hora 
          AND id_estado IN (1, 2, 3)
          AND id_cita != OLD.id_cita; -- Excluimos la cita actual que se está modificando

        IF v_choque_medico > 0 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Error de Negocio: El médico ya tiene una cita agendada en esa nueva fecha y hora.';
        END IF;

        SELECT COUNT(*) INTO v_choque_paciente FROM cita
        WHERE rut_paciente = NEW.rut_paciente 
          AND fecha_hora = NEW.fecha_hora 
          AND id_estado IN (1, 2, 3)
          AND id_cita != OLD.id_cita;

        IF v_choque_paciente > 0 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Error de Negocio: El paciente ya tiene otra cita en esa nueva fecha y hora.';
        END IF;
        
    END IF;
END //

DELIMITER ;

-- C. VISTA (Búsqueda Avanzada y Mis Citas)
-- Genera el resumen claro exigido en la pauta y trae todos los campos necesarios para aplicar los filtros.
CREATE OR REPLACE VIEW vista_resumen_citas AS
SELECT 
    c.id_cita,
    c.fecha_hora,
    p.rut_paciente,
    p.nom_completo AS paciente,
    pr.nom_prevision AS prevision,
    m.rut_medico,
    m.nom_completo AS medico,
    e.id_especialidad,
    e.nom_especialidad AS especialidad,
    cm.codigo_interno AS cod_centro,
    cm.nombre AS centro_medico,
    cm.region,
    c.id_estado,
    ec.nom_estado AS estado
FROM 
    cita c
JOIN 
    paciente p ON c.rut_paciente = p.rut_paciente
JOIN 
    prevision pr ON p.id_prevision = pr.id_prevision
JOIN 
    medico m ON c.rut_medico = m.rut_medico
JOIN 
    especialidad e ON c.id_especialidad = e.id_especialidad
JOIN 
    centro_medico cm ON c.codigo_interno = cm.codigo_interno
JOIN 
    estado_cita ec ON c.id_estado = ec.id_estado;

-- D. FUNCIÓN SQL (Reglas de Negocio - CRUD Médicos)
-- Cuenta las especialidades y devuelve 1 (Verdadero) si puede agregar más, o 0 (Falso) si llegó al límite de 3.
DELIMITER //

CREATE FUNCTION fn_puede_agregar_especialidad(p_rut VARCHAR(12)) 
RETURNS TINYINT(1)
READS SQL DATA
BEGIN
    DECLARE v_cantidad INT;
    
    SELECT COUNT(*) INTO v_cantidad 
    FROM medico_especialidad 
    WHERE rut_medico = p_rut;
    
    IF v_cantidad >= 3 THEN
        RETURN 0; -- Falso (No puede agregar)
    ELSE
        RETURN 1; -- Verdadero (Sí puede agregar)
    END IF;
END //

-- Trigger que utiliza la función para bloquear el ingreso en la base de datos
CREATE TRIGGER trg_limite_especialidades
BEFORE INSERT ON medico_especialidad
FOR EACH ROW
BEGIN
    DECLARE v_permitido TINYINT(1);
    
    -- Llamamos a nuestra función SQL
    SET v_permitido = fn_puede_agregar_especialidad(NEW.rut_medico);
    
    IF v_permitido = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Error de Negocio: El médico ya ha alcanzado el límite máximo de 3 especialidades.';
    END IF;
END //

DELIMITER ;
