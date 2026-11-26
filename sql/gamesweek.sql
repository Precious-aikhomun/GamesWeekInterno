SET NAMES utf8mb4;

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
    tematica VARCHAR(60) NULL COMMENT 'Categoría o track: Game Design, eSports, Día Indie, Cosplay, Novedades...',
    titulo VARCHAR(180) NOT NULL,
    descripcion TEXT NOT NULL,
    tipo ENUM('charla', 'taller', 'torneo', 'actividad_libre') NOT NULL,
    fecha_inicio DATETIME NOT NULL,
    fecha_fin DATETIME NOT NULL,
    aforo_maximo INT UNSIGNED NULL COMMENT 'NULL = sin límite de aforo',
    requiere_inscripcion TINYINT(1) NOT NULL DEFAULT 1,
    datos_extra JSON NULL COMMENT 'Atributos específicos opcionales según tipo de actividad',
    INDEX idx_actividades_fecha_inicio (fecha_inicio),
    INDEX idx_actividades_tipo_inicio (tipo, fecha_inicio),
    INDEX idx_actividades_tematica (tematica),
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
    INDEX idx_partidas_act_ronda (actividad_id, ronda, numero_partida),
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
    INDEX idx_asistencias_insc_hora (inscripcion_id, hora_entrada),
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

-- ============================================================================
-- DATOS INICIALES DEL EVENTO (Game Week 2027 · CCC Madrid: 18-22 ene, 12:00-17:00)
-- ============================================================================

INSERT INTO salas (id, nombre, ubicacion, orden) VALUES
(1, 'Arena PS5', 'CCC Madrid · Planta 5 (Zona Gaming)', 1),
(2, 'Auditorio Tech', 'CCC Madrid · Planta 5 (Salón Principal)', 2),
(3, 'Aula Dev Lab', 'CCC Madrid · Planta 5 (Aulas Tech)', 3),
(4, 'Zona Indie & Cosplay', 'CCC Madrid · Planta 5 (Hall Central)', 4);

INSERT INTO videojuegos (id, nombre, genero, plataforma, pegi) VALUES
(1, 'EA Sports FC 25', 'Deportes', 'PlayStation 5', 3),
(2, 'Tekken 8', 'Lucha', 'PlayStation 5', 16),
(3, 'Mario Kart 8 Deluxe', 'Conducción', 'Nintendo Switch', 3),
(4, 'Valorant', 'Shooter Táctico', 'PC', 16);

INSERT INTO ponentes (id, nombre, apellidos, titular, empresa_o_rol, biografia, foto_url, web_personal, redes_sociales) VALUES
(1, 'Lucía', 'Fernández Vega', 'Diseño de mundos y narrativa interactiva', 'Lead Game Designer · Estudio Ibérico', 'Más de 10 años diseñando sistemas de juego y narrativa para títulos AA e indie publicados en PC y consola.', NULL, NULL, '{"linkedin": "https://linkedin.com", "twitter": "https://x.com"}'),
(2, 'Marcos', 'Serrano Gil', 'Arquitectura web y multijugador en tiempo real', 'Senior Gameplay Programmer · PixelForge', 'Especializado en motores open-source, WebGL y servidores autoritativos para juegos competitivos.', NULL, NULL, '{"github": "https://github.com", "linkedin": "https://linkedin.com"}'),
(3, 'Elena', 'Navarro Ruiz', 'Publicación indie y marketing comunitario', 'Productora Indie · Colectivo Madrid Dev', 'Mentora de equipos independientes en programas de incubación y lanzamiento en Steam y consolas.', NULL, NULL, '{"twitter": "https://x.com", "twitch": "https://twitch.tv"}'),
(4, 'Carlos', 'Méndez Torres', 'Audio espacial y diseño sonoro adaptativo', 'Audio Director · WaveCraft Studio', 'Compositor y diseñador de audio interactivo con FMOD y Wwise para experiencias inmersivas y VR.', NULL, NULL, '{"linkedin": "https://linkedin.com"}');

INSERT INTO actividades (id, sala_id, codigo_programa, tematica, titulo, descripcion, tipo, fecha_inicio, fecha_fin, aforo_maximo, requiere_inscripcion, datos_extra) VALUES
(1, 2, 'Ponencia 1', 'Novedades', 'Apertura Game Week 2027: Novedades de la Industria', 'Charla inaugural sobre las tendencias tecnológicas, motores gráficos y salidas profesionales en desarrollo de videojuegos para 2027.', 'charla', '2027-01-18 12:00:00', '2027-01-18 13:30:00', 80, 1, '{"nivel": "Todos los públicos", "destacada": true}'),
(2, 3, 'Taller 1.1', 'Game Design', 'Prototipado rápido de videojuegos en la web', 'Taller práctico paso a paso para construir un prototipo jugable en navegador desde cero con arquitectura limpia.', 'taller', '2027-01-18 14:00:00', '2027-01-18 16:30:00', 24, 1, '{"requisitos": "Conocimientos básicos de programación", "material": "Equipo disponible en el aula"}'),
(3, 1, 'Torneo 1', 'eSports', 'Copa Inaugural EA Sports FC 25 (PS5)', 'Torneo oficial 1v1 en eliminación directa sobre PlayStation 5 con fase final en pantalla grande.', 'torneo', '2027-01-18 14:00:00', '2027-01-18 17:00:00', 16, 1, '{"por_equipos": false, "consola": "PS5"}'),
(4, 2, 'Ponencia 2', 'Game Design', 'IA y comportamientos emergentes en NPCs', 'Cómo diseñar enemigos y compañeros creíbles combinando máquinas de estados, árboles de comportamiento e IA generativa.', 'charla', '2027-01-19 12:00:00', '2027-01-19 13:30:00', 80, 1, '{"nivel": "Intermedio"}'),
(5, 1, 'Torneo 2', 'eSports', 'Torneo Oficial Tekken 8 (PS5)', 'Competición 1v1 al mejor de 3 combates en Arena PS5. Requiere edad mínima PEGI 16.', 'torneo', '2027-01-19 14:00:00', '2027-01-19 17:00:00', 16, 1, '{"por_equipos": false, "consola": "PS5"}'),
(6, 4, 'Expo Indie', 'Día Indie', 'Showcase Día Indie: Prueba proyectos de estudios y alumnos', 'Espacio abierto durante toda la jornada para probar demos jugables de desarrolladores independientes y proyectos DAW/3D de CCC.', 'actividad_libre', '2027-01-20 12:00:00', '2027-01-20 17:00:00', NULL, 0, '{"acceso": "Libre durante todo el horario"}'),
(7, 2, 'Ponencia 3', 'Día Indie', 'De proyecto de clase a Steam: guía indie realista', 'Experiencias reales de alcance, presupuesto, playtesting y lanzamiento de un primer juego independiente.', 'charla', '2027-01-20 13:00:00', '2027-01-20 14:30:00', 80, 1, '{"destacada": true}'),
(8, 1, 'Torneo 3', 'eSports', 'Grand Prix Mario Kart 8 Deluxe', 'Carreras eliminatorias a 150cc abiertas a todos los asistentes en modo local.', 'torneo', '2027-01-20 15:00:00', '2027-01-20 17:00:00', 16, 1, '{"por_equipos": false}'),
(9, 3, 'Taller 2.1', 'Audio & Arte', 'Diseño sonoro adaptativo para videojuegos', 'Creación de paisajes sonoros dinámicos que reaccionan a la acción del jugador en tiempo real.', 'taller', '2027-01-21 12:00:00', '2027-01-21 14:00:00', 20, 1, '{"requisitos": "Ninguno"}'),
(10, 3, 'Torneo 4', 'eSports', 'Reto Valorant 5v5 por Equipos', 'Partidas rápidas por equipos en PC. Indica el nombre de tu equipo al inscribirte (PEGI 16).', 'torneo', '2027-01-21 14:00:00', '2027-01-21 17:00:00', 20, 1, '{"por_equipos": true}'),
(11, 4, 'Cosplay', 'Cosplay', 'Exhibición, Concurso y Photocall Cosplay', 'Pasarela libre y sesión de fotos temática en el Hall Central con jurado invitado.', 'actividad_libre', '2027-01-22 12:00:00', '2027-01-22 15:00:00', NULL, 0, '{"acceso": "Libre", "premio_mejor_caracterizacion": true}'),
(12, 2, 'Clausura', 'Novedades', 'Novedades VR/Next-Gen y Entrega de Premios', 'Repaso a las tecnologías inmersivas emergentes y ceremonia oficial de entrega de trofeos de la Game Week 2027.', 'charla', '2027-01-22 15:30:00', '2027-01-22 17:00:00', 80, 1, '{"destacada": true}');

INSERT INTO actividad_ponentes (actividad_id, ponente_id, rol) VALUES
(1, 1, 'Ponente'),
(1, 3, 'Moderador'),
(2, 2, 'Ponente'),
(4, 1, 'Ponente'),
(4, 2, 'Invitado'),
(7, 3, 'Ponente'),
(9, 4, 'Ponente'),
(12, 1, 'Moderador'),
(12, 4, 'Ponente');

INSERT INTO torneos_detalle (actividad_id, videojuego_id, formato, premios, reglas) VALUES
(3, 1, '1v1 Eliminación directa', 'Trofeo Game Week 2027 + Periférico gaming para el 1.º y 2.º puesto', 'Partidos de 5 minutos por parte. Controles estándar, equipos de club oficiales, empate resuelto en prórroga y penaltis.'),
(5, 2, '1v1 Al mejor de 3 (BO3)', 'Trofeo Game Week 2027 + Tarjeta regalo PlayStation Store', 'Combates estándar a 3 rondas de 60 segundos. Prohibido pausar durante la ronda. Final al mejor de 5.'),
(8, 3, 'Grupos de 4 jugadores (150cc)', 'Trofeo Game Week 2027 + Merchandising oficial', 'Copa de 4 carreras por ronda, objetos normales y sin ayuda de volante inteligente.'),
(10, 4, '5v5 Eliminación directa', 'Trofeo al equipo campeón + Packs gaming para el quinteto ganador', 'Formato Swiftplay hasta semifinales; gran final a 13 rondas en mapa elegido por veto.');

INSERT INTO torneo_partidas (actividad_id, ronda, numero_partida, inscripcion_1_id, inscripcion_2_id, puntos_1, puntos_2, ganador_inscripcion_id, estado_partida) VALUES
(3, 'Semifinal', 1, NULL, NULL, 0, 0, NULL, 'pendiente'),
(3, 'Semifinal', 2, NULL, NULL, 0, 0, NULL, 'pendiente'),
(3, 'Final', 1, NULL, NULL, 0, 0, NULL, 'pendiente'),
(5, 'Semifinal', 1, NULL, NULL, 0, 0, NULL, 'pendiente'),
(5, 'Semifinal', 2, NULL, NULL, 0, 0, NULL, 'pendiente'),
(5, 'Final', 1, NULL, NULL, 0, 0, NULL, 'pendiente');
