USE gestion_documentos;
SET NAMES utf8mb4;

SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE documentos_archivos;
TRUNCATE TABLE documentos;
SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO documentos (id_documento, no_oficio, fecha, asunto, remitente, destinatario, estatus, archivo_pdf) VALUES
  (1, '001/2026', '2026-10-05', 'Solicitud de materiales de oficina', 'Dirección Administrativa', 'Coordinación Académica', 'Recibido', 'oficio_001-2026.pdf'),
  (2, '002/2026', '2026-10-04', 'Invitación a evento de aniversario', 'Secretaría General', 'Dirección ETDA', 'En proceso', 'oficio_002-2026.pdf'),
  (3, '003/2026', '2026-10-03', 'Reporte de actividades del trimestre', 'Departamento de Sistemas', 'Dirección ETDA', 'Atendido', 'oficio_003-2026.pdf'),
  (4, '004/2026', '2026-10-02', 'Solicitud de apoyo para congreso', 'Institución Externa', 'Coordinación de Investigación', 'Recibido', 'oficio_004-2026.pdf'),
  (5, '005/2026', '2026-10-01', 'Oficio de colaboración interinstitucional', 'C. Municipal de Tapachula', 'Rectoría', 'En proceso', 'oficio_005-2026.pdf'),
  (6, '006/2026', '2026-09-29', 'Circular sobre calendario de exámenes', 'Secretaría Académica', 'Coordinación de Licenciatura', 'Atendido', 'oficio_006-2026.pdf'),
  (7, '007/2026', '2026-09-26', 'Comunicado de mantenimiento eléctrico', 'Departamento de Mantenimiento', 'Dirección ETDA', 'Atendido', 'oficio_007-2026.pdf'),
  (8, '008/2026', '2026-09-24', 'Solicitud de constancias de estudio', 'Sociedad de Alumnos', 'Control Escolar', 'Recibido', 'oficio_008-2026.pdf');
