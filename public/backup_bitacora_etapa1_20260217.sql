-- MySQL dump 10.13  Distrib 8.0.45, for Linux (x86_64)
--
-- Host: localhost    Database: trazabilidad_local
-- ------------------------------------------------------
-- Server version	8.0.45-0ubuntu0.24.04.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `bitacora`
--

DROP TABLE IF EXISTS `bitacora`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bitacora` (
  `id_evento` int NOT NULL AUTO_INCREMENT COMMENT 'Identificador único del evento (PK)',
  `serial_equipo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Serial del equipo (FK a equipos.serial). Trazabilidad del activo.',
  `id_lugar` int DEFAULT NULL COMMENT 'Referencia a lugares (FK). Ubicación física estructurada.',
  `hostname` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'PENDIENTE' COMMENT 'Nombre de equipo en red Active Directory. Puede cambiar si hay reimaging.',
  `sede` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ubicacion` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `campo_adic1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Campo adicional flexible para información contextual del evento',
  `desc_evento` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Descripción textual del evento (ej: "Asignación de equipo por renovación")',
  `correo_responsable` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Correo del responsable final del equipo (FK a usuarios_sistema.correo_ldap). DEBE existir en usuarios_sistema.',
  `responsable_secundario` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Correo de responsable secundario opcional (ej: supervisor, co-responsable)',
  `check_sccm` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Validación: equipo registrado en System Center Configuration Manager (0=No, 1=Sí)',
  `check_dlo` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Validación: equipo tiene Data Lifecycle Optimizer configurado (0=No, 1=Sí)',
  `check_antivirus` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Validación: equipo tiene antivirus activo y actualizado (0=No, 1=Sí)',
  `fecha_evento` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Timestamp exacto del evento',
  `tipo_evento` enum('Alta','Alistamiento','Asignación','Devolución','Baja','Asignacion_Masiva') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Tipo de movimiento: Alta=ingreso inicial, Alistamiento=preparación técnica, Asignación=entrega a usuario, Devolución=regreso a bodega, Baja=retiro definitivo, Asignacion_Masiva=múltiples equipos simultáneos',
  `tecnico_responsable` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Correo del técnico que registra el evento. Puede ser NULL. No tiene FK para permitir valores como "SYSTEM".',
  PRIMARY KEY (`id_evento`),
  KEY `fk_bitacora_lugar` (`id_lugar`),
  KEY `idx_bitacora_serial_evento` (`serial_equipo`,`id_evento` DESC),
  KEY `idx_bitacora_responsable` (`correo_responsable`),
  CONSTRAINT `fk_bitacora_equipos` FOREIGN KEY (`serial_equipo`) REFERENCES `equipos` (`serial`) ON UPDATE CASCADE,
  CONSTRAINT `fk_bitacora_lugar` FOREIGN KEY (`id_lugar`) REFERENCES `lugares` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registro cronológico de eventos de equipos. Campos sede_snapshot/ubicacion_snapshot preservan contexto histórico exacto del momento del evento (crítico para auditorías legales). REQUIERE usuarios registrados en usuarios_sistema.';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bitacora`
--

LOCK TABLES `bitacora` WRITE;
/*!40000 ALTER TABLE `bitacora` DISABLE KEYS */;
INSERT INTO `bitacora` VALUES (1,'SNLEN2026012',55,'SNLEN2026012','Bodega tecnología','Bodega de Tecnología',NULL,'OC: 2025-0789-OC','guillermo.fonseca',NULL,0,0,0,'2026-02-16 16:00:07','Alta','guillermo.fonseca'),(2,'SNLEN2026013',55,'SNLEN2026013','Bodega tecnología','Bodega de Tecnología',NULL,'OC: 2025-0789-OC','guillermo.fonseca',NULL,0,0,0,'2026-02-16 16:01:22','Alta','guillermo.fonseca'),(3,'SNLEN2026014',55,'SNLEN2026014','Bodega tecnología','Bodega de Tecnología',NULL,'OC: 2025-0789-OC','guillermo.fonseca',NULL,0,0,0,'2026-02-16 16:01:22','Alta','guillermo.fonseca'),(4,'SNLEN2026015',55,'SNLEN2026015','Bodega tecnología','Bodega de Tecnología',NULL,'OC: 2025-0789-OC','guillermo.fonseca',NULL,0,0,0,'2026-02-16 16:01:22','Alta','guillermo.fonseca'),(5,'SNLEN2026016',55,'SNLEN2026016','Bodega tecnología','Bodega de Tecnología',NULL,'OC: 2025-0789-OC','guillermo.fonseca',NULL,0,0,0,'2026-02-16 16:01:22','Alta','guillermo.fonseca'),(6,'SNLEN2026017',55,'SNLEN2026017','Bodega tecnología','Bodega de Tecnología',NULL,'OC: 2025-0789-OC','guillermo.fonseca',NULL,0,0,0,'2026-02-16 16:01:22','Alta','guillermo.fonseca'),(7,'SNLEN2026018',55,'SNLEN2026018','Bodega tecnología','Bodega de Tecnología',NULL,'OC: 2025-0789-OC','guillermo.fonseca',NULL,0,0,0,'2026-02-16 16:01:22','Alta','guillermo.fonseca'),(8,'SNLEN2026019',55,'SNLEN2026019','Bodega tecnología','Bodega de Tecnología',NULL,'OC: 2025-0789-OC','guillermo.fonseca',NULL,0,0,0,'2026-02-16 16:01:22','Alta','guillermo.fonseca'),(9,'SNLEN2026020',55,'SNLEN2026020','Bodega tecnología','Bodega de Tecnología',NULL,'OC: 2025-0789-OC','guillermo.fonseca',NULL,0,0,0,'2026-02-16 16:01:22','Alta','guillermo.fonseca'),(10,'SNLEN2026021',55,'SNLEN2026021','Bodega tecnología','Bodega de Tecnología',NULL,'OC: 2025-0789-OC','guillermo.fonseca',NULL,0,0,0,'2026-02-16 16:01:22','Alta','guillermo.fonseca'),(11,'SNLEN2026022',55,'SNLEN2026022','Bodega tecnología','Bodega de Tecnología',NULL,'OC: 2025-0789-OC','guillermo.fonseca',NULL,0,0,0,'2026-02-16 16:01:22','Alta','guillermo.fonseca'),(12,'SNLEN2026023',55,'SNLEN2026023','Bodega tecnología','Bodega de Tecnología',NULL,'OC: 2025-0789-OC','guillermo.fonseca',NULL,0,0,0,'2026-02-16 16:01:22','Alta','guillermo.fonseca'),(13,'SNLEN2026024',55,'SNLEN2026024','Bodega tecnología','Bodega de Tecnología',NULL,'OC: 2025-0789-OC','guillermo.fonseca',NULL,0,0,0,'2026-02-16 16:01:22','Alta','guillermo.fonseca'),(14,'SNLEN2026025',55,'SNLEN2026025','Bodega tecnología','Bodega de Tecnología',NULL,'OC: 2025-0789-OC','guillermo.fonseca',NULL,0,0,0,'2026-02-16 16:01:22','Alta','guillermo.fonseca'),(15,'SNLEN2026026',55,'SNLEN2026026','Bodega tecnología','Bodega de Tecnología',NULL,'OC: 2025-0789-OC','guillermo.fonseca',NULL,0,0,0,'2026-02-16 16:01:22','Alta','guillermo.fonseca'),(16,'SNLEN2026027',55,'SNLEN2026027','Bodega tecnología','Bodega de Tecnología',NULL,'OC: 2025-0789-OC','guillermo.fonseca',NULL,0,0,0,'2026-02-16 16:01:22','Alta','guillermo.fonseca'),(17,'SNLEN2026028',55,'SNLEN2026028','Bodega tecnología','Bodega de Tecnología',NULL,'OC: 2025-0789-OC','guillermo.fonseca',NULL,0,0,0,'2026-02-16 16:01:22','Alta','guillermo.fonseca'),(18,'SNLEN2026029',55,'SNLEN2026029','Bodega tecnología','Bodega de Tecnología',NULL,'OC: 2025-0789-OC','guillermo.fonseca',NULL,0,0,0,'2026-02-16 16:01:22','Alta','guillermo.fonseca'),(19,'SNLEN2026030',55,'SNLEN2026030','Bodega tecnología','Bodega de Tecnología',NULL,'OC: 2025-0789-OC','guillermo.fonseca',NULL,0,0,0,'2026-02-16 16:01:22','Alta','guillermo.fonseca'),(20,'SNLEN2026031',55,'SNLEN2026031','Bodega tecnología','Bodega de Tecnología',NULL,'OC: 2025-0789-OC','guillermo.fonseca',NULL,0,0,0,'2026-02-16 16:01:22','Alta','guillermo.fonseca'),(21,'SNLEN2026032',55,'SNLEN2026032','Bodega tecnología','Bodega de Tecnología',NULL,'OC: 2025-0789-OC','guillermo.fonseca',NULL,0,0,0,'2026-02-16 16:01:22','Alta','guillermo.fonseca'),(22,'SNLEN2026013',22,'LOTE:20260216160203-251','Quinta de Mutis','Disposición Final',NULL,'Motivo Baja: Robo o pérdida del equipo','Activos Fijos',NULL,0,0,0,'2026-02-16 16:02:03','Baja','guillermo.fonseca'),(23,'SNLEN2026032',53,'NTA130020','SEIC','Módulo C','Equipo nuevo con teclado y mouse','Caso: 105080','guillermo.fonseca@lab.urosario.edu.co',NULL,1,1,1,'2026-02-16 16:02:56','Asignación','guillermo.fonseca'),(24,'SNLEN2026014',36,'QMA130002','Quinta de Mutis','Casa Vida Diaria','','Caso: 105081','gabriel.martinez@lab.urosario.edu.co',NULL,1,0,1,'2026-02-16 16:05:45','Asignacion_Masiva','guillermo.fonseca'),(25,'SNLEN2026015',36,'QMA130003','Quinta de Mutis','Casa Vida Diaria','','Caso: 105081','gabriel.martinez@lab.urosario.edu.co',NULL,1,0,1,'2026-02-16 16:05:45','Asignacion_Masiva','guillermo.fonseca'),(26,'SNLEN2026016',36,'QMA130004','Quinta de Mutis','Casa Vida Diaria','','Caso: 105081','gabriel.martinez@lab.urosario.edu.co',NULL,1,0,1,'2026-02-16 16:05:45','Asignacion_Masiva','guillermo.fonseca'),(27,'SNLEN2026017',36,'QMA130005','Quinta de Mutis','Casa Vida Diaria','','Caso: 105081','gabriel.martinez@lab.urosario.edu.co',NULL,1,0,1,'2026-02-16 16:05:45','Asignacion_Masiva','guillermo.fonseca'),(28,'SNLEN2026018',36,'QMA130006','Quinta de Mutis','Casa Vida Diaria','','Caso: 105081','gabriel.martinez@lab.urosario.edu.co',NULL,1,0,1,'2026-02-16 16:05:45','Asignacion_Masiva','guillermo.fonseca'),(29,'SNLEN2026019',36,'QMA130007','Quinta de Mutis','Casa Vida Diaria','Nuevo','Caso: 105081','gabriel.martinez@lab.urosario.edu.co',NULL,1,0,1,'2026-02-16 16:05:45','Asignacion_Masiva','guillermo.fonseca'),(30,'SNLEN2026020',36,'QMA130008','Quinta de Mutis','Casa Vida Diaria','','Caso: 105081','gabriel.martinez@lab.urosario.edu.co',NULL,1,0,1,'2026-02-16 16:05:45','Asignacion_Masiva','guillermo.fonseca'),(31,'SNLEN2026021',36,'QMA130009','Quinta de Mutis','Casa Vida Diaria','','Caso: 105081','gabriel.martinez@lab.urosario.edu.co',NULL,1,0,1,'2026-02-16 16:05:45','Asignacion_Masiva','guillermo.fonseca'),(32,'SNLEN2026022',36,'QMA130010','Quinta de Mutis','Casa Vida Diaria','','Caso: 105081','gabriel.martinez@lab.urosario.edu.co',NULL,1,0,1,'2026-02-16 16:05:45','Asignacion_Masiva','guillermo.fonseca'),(33,'SNLEN2026023',36,'QMA130011','Quinta de Mutis','Casa Vida Diaria','','Caso: 105081','gabriel.martinez@lab.urosario.edu.co',NULL,1,0,1,'2026-02-16 16:05:45','Asignacion_Masiva','guillermo.fonseca'),(34,'SNLEN2026024',36,'QMA130012','Quinta de Mutis','Casa Vida Diaria','','Caso: 105081','gabriel.martinez@lab.urosario.edu.co',NULL,1,0,1,'2026-02-16 16:05:45','Asignacion_Masiva','guillermo.fonseca'),(35,'SNLEN2026025',36,'QMA130013','Quinta de Mutis','Casa Vida Diaria','','Caso: 105081','gabriel.martinez@lab.urosario.edu.co',NULL,1,0,1,'2026-02-16 16:05:45','Asignacion_Masiva','guillermo.fonseca'),(36,'SNLEN2026026',36,'QMA130014','Quinta de Mutis','Casa Vida Diaria','','Caso: 105081','gabriel.martinez@lab.urosario.edu.co',NULL,1,0,1,'2026-02-16 16:05:45','Asignacion_Masiva','guillermo.fonseca'),(37,'SNLEN2026027',36,'QMA130015','Quinta de Mutis','Casa Vida Diaria','','Caso: 105081','gabriel.martinez@lab.urosario.edu.co',NULL,1,0,1,'2026-02-16 16:05:45','Asignacion_Masiva','guillermo.fonseca'),(38,'SNLEN2026028',36,'QMA130016','Quinta de Mutis','Casa Vida Diaria','','Caso: 105081','gabriel.martinez@lab.urosario.edu.co',NULL,1,0,1,'2026-02-16 16:05:45','Asignacion_Masiva','guillermo.fonseca'),(39,'SNLEN2026029',36,'QMA130017','Quinta de Mutis','Casa Vida Diaria','','Caso: 105081','gabriel.martinez@lab.urosario.edu.co',NULL,1,0,1,'2026-02-16 16:05:45','Asignacion_Masiva','guillermo.fonseca'),(40,'SNLEN2026030',36,'QMA130018','Quinta de Mutis','Casa Vida Diaria','','Caso: 105081','gabriel.martinez@lab.urosario.edu.co',NULL,1,0,1,'2026-02-16 16:05:45','Asignacion_Masiva','guillermo.fonseca'),(41,'SNLEN2026031',36,'QMA130019','Quinta de Mutis','Casa Vida Diaria','','Caso: 105081','gabriel.martinez@lab.urosario.edu.co',NULL,1,0,1,'2026-02-16 16:05:45','Asignacion_Masiva','guillermo.fonseca'),(42,'SNLEN2026032',36,'QMA130020','Quinta de Mutis','Casa Vida Diaria','','Caso: 105081','gabriel.martinez@lab.urosario.edu.co',NULL,1,0,1,'2026-02-16 16:05:45','Asignacion_Masiva','guillermo.fonseca');
/*!40000 ALTER TABLE `bitacora` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-02-17  8:24:45
