CREATE DATABASE IF NOT EXISTS gamesweek
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE gamesweek;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS asistencias;
DROP TABLE IF EXISTS torneo_partidas;
DROP TABLE IF EXISTS inscripciones;
DROP TABLE IF EXISTS torneos_detalle;
DROP TABLE IF EXISTS actividad_ponentes;
DROP TABLE IF EXISTS actividades;
DROP TABLE IF EXISTS videojuegos;
DROP TABLE IF EXISTS salas;
DROP TABLE IF EXISTS ponentes;
DROP TABLE IF EXISTS usuarios;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(80) NOT NULL,
    apellidos VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL COMMENT 'Hash generado con password_hash()',
    telefono VARCHAR(25) NULL,
    fecha_nacimiento DATE NOT NULL,
    nickname VARCHAR(50) NULL COMMENT 'Se rellena con UPDATE al inscribirse en el primer torneo',
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ponentes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(80) NOT NULL,
    apellidos VARCHAR(120) NOT NULL,
    titular VARCHAR(180) NOT NULL COMMENT 'Subtítulo corto para tarjeta del programa',
    empresa_o_rol VARCHAR(150) NOT NULL,
    biografia TEXT NOT NULL,
    foto_url VARCHAR(255) NULL,
    web_personal VARCHAR(255) NULL,
    redes_sociales JSON NULL COMMENT 'Ej: {"twitter": "...", "linkedin": "...", "twitch": "..."}'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE salas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(80) NOT NULL UNIQUE,
    ubicacion VARCHAR(120) NULL,
    orden TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Orden de columnas en la parrilla horaria'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE videojuegos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    genero VARCHAR(60) NOT NULL,
    plataforma VARCHAR(80) NOT NULL,
    pegi TINYINT UNSIGNED NOT NULL DEFAULT 3 COMMENT 'Edad mínima recomendada (3, 7, 12, 16, 18)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE actividades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sala_id INT NULL,
    codigo_programa VARCHAR(30) NULL COMMENT 'Etiqueta visual: Ponencia 1, Taller 1.1, Torneo 1',
    tematica VARCHAR(60) NULL COMMENT 'Categoría o track: Game Design, eSports, IA...',
    titulo VARCHAR(180) NOT NULL,
    descripcion TEXT NOT NULL,
    tipo ENUM('charla', 'taller', 'torneo', 'actividad_libre') NOT NULL,
    fecha_inicio DATETIME NOT NULL,
    fecha_fin DATETIME NOT NULL,
    aforo_maximo INT UNSIGNED NULL COMMENT 'NULL = sin límite de aforo',
    requiere_inscripcion TINYINT(1) NOT NULL DEFAULT 1,
    datos_extra JSON NULL COMMENT 'Atributos específicos opcionales según tipo de actividad',
    CONSTRAINT fk_actividades_sala
        FOREIGN KEY (sala_id) REFERENCES salas(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE actividad_ponentes (
    actividad_id INT NOT NULL,
    ponente_id INT NOT NULL,
    rol VARCHAR(50) NOT NULL DEFAULT 'Ponente' COMMENT 'Ponente, Moderador, Invitado',
    PRIMARY KEY (actividad_id, ponente_id),
    CONSTRAINT fk_act_pon_actividad
        FOREIGN KEY (actividad_id) REFERENCES actividades(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_act_pon_ponente
        FOREIGN KEY (ponente_id) REFERENCES ponentes(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE torneos_detalle (
    actividad_id INT PRIMARY KEY,
    videojuego_id INT NOT NULL,
    formato VARCHAR(60) NOT NULL COMMENT 'Ej: 1v1 Eliminación directa, 5v5 Fase de grupos',
    premios TEXT NOT NULL,
    reglas TEXT NOT NULL,
    CONSTRAINT fk_torneos_actividad
        FOREIGN KEY (actividad_id) REFERENCES actividades(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_torneos_videojuego
        FOREIGN KEY (videojuego_id) REFERENCES videojuegos(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inscripciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    actividad_id INT NOT NULL,
    nombre_equipo VARCHAR(100) NULL COMMENT 'Opcional: solo para torneos por equipos',
    fecha_inscripcion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    estado ENUM('confirmada', 'lista_espera', 'cancelada') NOT NULL DEFAULT 'confirmada',
    codigo_acceso VARCHAR(20) NOT NULL UNIQUE COMMENT 'Código corto o token QR para check-in',
    UNIQUE KEY uk_usuario_actividad (usuario_id, actividad_id),
    INDEX idx_inscripciones_act_estado_fecha (actividad_id, estado, fecha_inscripcion),
    CONSTRAINT fk_inscripciones_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_inscripciones_actividad
        FOREIGN KEY (actividad_id) REFERENCES actividades(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE torneo_partidas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actividad_id INT NOT NULL,
    ronda VARCHAR(50) NOT NULL COMMENT 'Octavos, Cuartos, Semifinal, Final',
    numero_partida TINYINT UNSIGNED NOT NULL,
    inscripcion_1_id INT NULL,
    inscripcion_2_id INT NULL,
    puntos_1 TINYINT UNSIGNED NOT NULL DEFAULT 0,
    puntos_2 TINYINT UNSIGNED NOT NULL DEFAULT 0,
    ganador_inscripcion_id INT NULL,
    estado_partida ENUM('pendiente', 'en_juego', 'finalizada') NOT NULL DEFAULT 'pendiente',
    CONSTRAINT fk_partidas_torneo
        FOREIGN KEY (actividad_id) REFERENCES torneos_detalle(actividad_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_partidas_insc_1
        FOREIGN KEY (inscripcion_1_id) REFERENCES inscripciones(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_partidas_insc_2
        FOREIGN KEY (inscripcion_2_id) REFERENCES inscripciones(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_partidas_ganador
        FOREIGN KEY (ganador_inscripcion_id) REFERENCES inscripciones(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE asistencias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    actividad_id INT NULL COMMENT 'NULL = acceso libre al recinto general',
    inscripcion_id INT NULL COMMENT 'NULL = asistente sin reserva previa',
    hora_entrada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tipo_asistencia ENUM('con_inscripcion', 'acceso_libre') NOT NULL,
    CONSTRAINT fk_asistencias_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_asistencias_actividad
        FOREIGN KEY (actividad_id) REFERENCES actividades(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_asistencias_inscripcion
        FOREIGN KEY (inscripcion_id) REFERENCES inscripciones(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
