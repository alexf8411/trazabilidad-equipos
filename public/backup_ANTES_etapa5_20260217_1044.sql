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
-- Table structure for table `auditoria_acceso`
--

DROP TABLE IF EXISTS `auditoria_acceso`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `auditoria_acceso` (
  `id_log` int NOT NULL AUTO_INCREMENT COMMENT 'Identificador único del log (PK)',
  `fecha` date NOT NULL COMMENT 'Fecha del intento de acceso (indexado para consultas por rango)',
  `hora` time NOT NULL COMMENT 'Hora exacta del intento de acceso',
  `usuario_ldap` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Usuario LDAP que intentó acceder (indexado)',
  `ip_acceso` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Dirección IP de origen (soporta IPv4 e IPv6)',
  `accion` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Login Exitoso' COMMENT 'Tipo de acción: Login Exitoso, Login Fallido, Logout, etc.',
  PRIMARY KEY (`id_log`),
  KEY `usuario_ldap` (`usuario_ldap`),
  KEY `fecha` (`fecha`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log de inicio de sesión (compliance y seguridad). Retención recomendada: 90 días.';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `auditoria_acceso`
--

LOCK TABLES `auditoria_acceso` WRITE;
/*!40000 ALTER TABLE `auditoria_acceso` DISABLE KEYS */;
INSERT INTO `auditoria_acceso` VALUES (1,'2026-02-17','08:19:03','guillermo.fonseca','10.212.134.9','Login Exitoso'),(2,'2026-02-17','10:26:01','guillermo.fonseca','10.212.134.9','Modificó configuración del sistema');
/*!40000 ALTER TABLE `auditoria_acceso` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `auditoria_cambios`
--

DROP TABLE IF EXISTS `auditoria_cambios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `auditoria_cambios` (
  `id_cambio` int NOT NULL AUTO_INCREMENT COMMENT 'Identificador único del cambio (PK)',
  `usuario_responsable` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Usuario que ejecutó el cambio (sesión LDAP)',
  `tipo_accion` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Tipo de acción ejecutada (ej: UPDATE REVERT, EDICION MAESTRA, REVERSION_BAJA)',
  `referencia` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Identificador del objeto afectado (ej: Equipo: SNHP2025523)',
  `detalles` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Descripción detallada del cambio realizado',
  `ip_origen` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Dirección IP desde donde se ejecutó el cambio',
  `fecha` datetime DEFAULT CURRENT_TIMESTAMP COMMENT 'Timestamp del cambio',
  PRIMARY KEY (`id_cambio`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log de cambios administrativos críticos (ediciones, reversiones, configuración). Retención: indefinida.';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `auditoria_cambios`
--

LOCK TABLES `auditoria_cambios` WRITE;
/*!40000 ALTER TABLE `auditoria_cambios` DISABLE KEYS */;
INSERT INTO `auditoria_cambios` VALUES (1,'guillermo.fonseca','UPDATE REVERT','Equipo: SNLEN2026032','Reversión administrativa de Baja a Alta. Placa: 130020','10.212.134.9','2026-02-17 08:49:39'),(2,'guillermo.fonseca','UPDATE REVERT','Equipo: SNLEN2026013','Reversión administrativa de Baja a Alta. Placa: 130001','10.212.134.9','2026-02-17 09:15:49');
/*!40000 ALTER TABLE `auditoria_cambios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bitacora`
--

DROP TABLE IF EXISTS `bitacora`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bitacora` (
  `id_evento` int NOT NULL AUTO_INCREMENT COMMENT 'Identificador único del evento (PK)',
  `serial_equipo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Serial del equipo (FK a equipos.serial). Trazabilidad del activo.',
  `id_lugar` int NOT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=59 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registro cronológico de eventos de equipos. Campos sede_snapshot/ubicacion_snapshot preservan contexto histórico exacto del momento del evento (crítico para auditorías legales). REQUIERE usuarios registrados en usuarios_sistema.';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bitacora`
--

LOCK TABLES `bitacora` WRITE;
/*!40000 ALTER TABLE `bitacora` DISABLE KEYS */;
INSERT INTO `bitacora` VALUES (1,'SNLEN2026012',55,'SNLEN2026012','Bodega tecnología','Bodega de Tecnología',NULL,'OC: 2025-0789-OC','guillermo.fonseca',NULL,0,0,0,'2026-02-16 16:00:07','Alta','guillermo.fonseca'),(2,'SNLEN2026013',55,'SNLEN2026013','Bodega tecnología','Bodega de Tecnología',NULL,'OC: 2025-0789-OC','guillermo.fonseca',NULL,0,0,0,'2026-02-16 16:01:22','Alta','guillermo.fonseca'),(3,'SNLEN2026014',55,'SNLEN2026014','Bodega tecnología','Bodega de Tecnología',NULL,'OC: 2025-0789-OC','guillermo.fonseca',NULL,0,0,0,'2026-02-16 16:01:22','Alta','guillermo.fonseca'),(4,'SNLEN2026015',55,'SNLEN2026015','Bodega tecnología','Bodega de Tecnología',NULL,'OC: 2025-0789-OC','guillermo.fonseca',NULL,0,0,0,'2026-02-16 16:01:22','Alta','guillermo.fonseca'),(5,'SNLEN2026016',55,'SNLEN2026016','Bodega tecnología','Bodega de Tecnología',NULL,'OC: 2025-0789-OC','guillermo.fonseca',NULL,0,0,0,'2026-02-16 16:01:22','Alta','guillermo.fonseca'),(6,'SNLEN2026017',55,'SNLEN2026017','Bodega tecnología','Bodega de Tecnología',NULL,'OC: 2025-0789-OC','guillermo.fonseca',NULL,0,0,0,'2026-02-16 16:01:22','Alta','guillermo.fonseca'),(7,'SNLEN2026018',55,'SNLEN2026018','Bodega tecnología','Bodega de Tecnología',NULL,'OC: 2025-0789-OC','guillermo.fonseca',NULL,0,0,0,'2026-02-16 16:01:22','Alta','guillermo.fonseca'),(8,'SNLEN2026019',55,'SNLEN2026019','Bodega tecnología','Bodega de Tecnología',NULL,'OC: 2025-0789-OC','guillermo.fonseca',NULL,0,0,0,'2026-02-16 16:01:22','Alta','guillermo.fonseca'),(9,'SNLEN2026020',55,'SNLEN2026020','Bodega tecnología','Bodega de Tecnología',NULL,'OC: 2025-0789-OC','guillermo.fonseca',NULL,0,0,0,'2026-02-16 16:01:22','Alta','guillermo.fonseca'),(10,'SNLEN2026021',55,'SNLEN2026021','Bodega tecnología','Bodega de Tecnología',NULL,'OC: 2025-0789-OC','guillermo.fonseca',NULL,0,0,0,'2026-02-16 16:01:22','Alta','guillermo.fonseca'),(11,'SNLEN2026022',55,'SNLEN2026022','Bodega tecnología','Bodega de Tecnología',NULL,'OC: 2025-0789-OC','guillermo.fonseca',NULL,0,0,0,'2026-02-16 16:01:22','Alta','guillermo.fonseca'),(12,'SNLEN2026023',55,'SNLEN2026023','Bodega tecnología','Bodega de Tecnología',NULL,'OC: 2025-0789-OC','guillermo.fonseca',NULL,0,0,0,'2026-02-16 16:01:22','Alta','guillermo.fonseca'),(13,'SNLEN2026024',55,'SNLEN2026024','Bodega tecnología','Bodega de Tecnología',NULL,'OC: 2025-0789-OC','guillermo.fonseca',NULL,0,0,0,'2026-02-16 16:01:22','Alta','guillermo.fonseca'),(14,'SNLEN2026025',55,'SNLEN2026025','Bodega tecnología','Bodega de Tecnología',NULL,'OC: 2025-0789-OC','guillermo.fonseca',NULL,0,0,0,'2026-02-16 16:01:22','Alta','guillermo.fonseca'),(15,'SNLEN2026026',55,'SNLEN2026026','Bodega tecnología','Bodega de Tecnología',NULL,'OC: 2025-0789-OC','guillermo.fonseca',NULL,0,0,0,'2026-02-16 16:01:22','Alta','guillermo.fonseca'),(16,'SNLEN2026027',55,'SNLEN2026027','Bodega tecnología','Bodega de Tecnología',NULL,'OC: 2025-0789-OC','guillermo.fonseca',NULL,0,0,0,'2026-02-16 16:01:22','Alta','guillermo.fonseca'),(17,'SNLEN2026028',55,'SNLEN2026028','Bodega tecnología','Bodega de Tecnología',NULL,'OC: 2025-0789-OC','guillermo.fonseca',NULL,0,0,0,'2026-02-16 16:01:22','Alta','guillermo.fonseca'),(18,'SNLEN2026029',55,'SNLEN2026029','Bodega tecnología','Bodega de Tecnología',NULL,'OC: 2025-0789-OC','guillermo.fonseca',NULL,0,0,0,'2026-02-16 16:01:22','Alta','guillermo.fonseca'),(19,'SNLEN2026030',55,'SNLEN2026030','Bodega tecnología','Bodega de Tecnología',NULL,'OC: 2025-0789-OC','guillermo.fonseca',NULL,0,0,0,'2026-02-16 16:01:22','Alta','guillermo.fonseca'),(20,'SNLEN2026031',55,'SNLEN2026031','Bodega tecnología','Bodega de Tecnología',NULL,'OC: 2025-0789-OC','guillermo.fonseca',NULL,0,0,0,'2026-02-16 16:01:22','Alta','guillermo.fonseca'),(21,'SNLEN2026032',55,'SNLEN2026032','Bodega tecnología','Bodega de Tecnología',NULL,'OC: 2025-0789-OC','guillermo.fonseca',NULL,0,0,0,'2026-02-16 16:01:22','Alta','guillermo.fonseca'),(22,'SNLEN2026013',22,'LOTE:20260216160203-251','Quinta de Mutis','Disposición Final',NULL,'Motivo Baja: Robo o pérdida del equipo','Activos Fijos',NULL,0,0,0,'2026-02-16 16:02:03','Baja','guillermo.fonseca'),(23,'SNLEN2026032',53,'NTA130020','SEIC','Módulo C','Equipo nuevo con teclado y mouse','Caso: 105080','guillermo.fonseca@lab.urosario.edu.co',NULL,1,1,1,'2026-02-16 16:02:56','Asignación','guillermo.fonseca'),(24,'SNLEN2026014',36,'QMA130002','Quinta de Mutis','Casa Vida Diaria','','Caso: 105081','gabriel.martinez@lab.urosario.edu.co',NULL,1,0,1,'2026-02-16 16:05:45','Asignacion_Masiva','guillermo.fonseca'),(25,'SNLEN2026015',36,'QMA130003','Quinta de Mutis','Casa Vida Diaria','','Caso: 105081','gabriel.martinez@lab.urosario.edu.co',NULL,1,0,1,'2026-02-16 16:05:45','Asignacion_Masiva','guillermo.fonseca'),(26,'SNLEN2026016',36,'QMA130004','Quinta de Mutis','Casa Vida Diaria','','Caso: 105081','gabriel.martinez@lab.urosario.edu.co',NULL,1,0,1,'2026-02-16 16:05:45','Asignacion_Masiva','guillermo.fonseca'),(27,'SNLEN2026017',36,'QMA130005','Quinta de Mutis','Casa Vida Diaria','','Caso: 105081','gabriel.martinez@lab.urosario.edu.co',NULL,1,0,1,'2026-02-16 16:05:45','Asignacion_Masiva','guillermo.fonseca'),(28,'SNLEN2026018',36,'QMA130006','Quinta de Mutis','Casa Vida Diaria','','Caso: 105081','gabriel.martinez@lab.urosario.edu.co',NULL,1,0,1,'2026-02-16 16:05:45','Asignacion_Masiva','guillermo.fonseca'),(29,'SNLEN2026019',36,'QMA130007','Quinta de Mutis','Casa Vida Diaria','Nuevo','Caso: 105081','gabriel.martinez@lab.urosario.edu.co',NULL,1,0,1,'2026-02-16 16:05:45','Asignacion_Masiva','guillermo.fonseca'),(30,'SNLEN2026020',36,'QMA130008','Quinta de Mutis','Casa Vida Diaria','','Caso: 105081','gabriel.martinez@lab.urosario.edu.co',NULL,1,0,1,'2026-02-16 16:05:45','Asignacion_Masiva','guillermo.fonseca'),(31,'SNLEN2026021',36,'QMA130009','Quinta de Mutis','Casa Vida Diaria','','Caso: 105081','gabriel.martinez@lab.urosario.edu.co',NULL,1,0,1,'2026-02-16 16:05:45','Asignacion_Masiva','guillermo.fonseca'),(32,'SNLEN2026022',36,'QMA130010','Quinta de Mutis','Casa Vida Diaria','','Caso: 105081','gabriel.martinez@lab.urosario.edu.co',NULL,1,0,1,'2026-02-16 16:05:45','Asignacion_Masiva','guillermo.fonseca'),(33,'SNLEN2026023',36,'QMA130011','Quinta de Mutis','Casa Vida Diaria','','Caso: 105081','gabriel.martinez@lab.urosario.edu.co',NULL,1,0,1,'2026-02-16 16:05:45','Asignacion_Masiva','guillermo.fonseca'),(34,'SNLEN2026024',36,'QMA130012','Quinta de Mutis','Casa Vida Diaria','','Caso: 105081','gabriel.martinez@lab.urosario.edu.co',NULL,1,0,1,'2026-02-16 16:05:45','Asignacion_Masiva','guillermo.fonseca'),(35,'SNLEN2026025',36,'QMA130013','Quinta de Mutis','Casa Vida Diaria','','Caso: 105081','gabriel.martinez@lab.urosario.edu.co',NULL,1,0,1,'2026-02-16 16:05:45','Asignacion_Masiva','guillermo.fonseca'),(36,'SNLEN2026026',36,'QMA130014','Quinta de Mutis','Casa Vida Diaria','','Caso: 105081','gabriel.martinez@lab.urosario.edu.co',NULL,1,0,1,'2026-02-16 16:05:45','Asignacion_Masiva','guillermo.fonseca'),(37,'SNLEN2026027',36,'QMA130015','Quinta de Mutis','Casa Vida Diaria','','Caso: 105081','gabriel.martinez@lab.urosario.edu.co',NULL,1,0,1,'2026-02-16 16:05:45','Asignacion_Masiva','guillermo.fonseca'),(38,'SNLEN2026028',36,'QMA130016','Quinta de Mutis','Casa Vida Diaria','','Caso: 105081','gabriel.martinez@lab.urosario.edu.co',NULL,1,0,1,'2026-02-16 16:05:45','Asignacion_Masiva','guillermo.fonseca'),(39,'SNLEN2026029',36,'QMA130017','Quinta de Mutis','Casa Vida Diaria','','Caso: 105081','gabriel.martinez@lab.urosario.edu.co',NULL,1,0,1,'2026-02-16 16:05:45','Asignacion_Masiva','guillermo.fonseca'),(40,'SNLEN2026030',36,'QMA130018','Quinta de Mutis','Casa Vida Diaria','','Caso: 105081','gabriel.martinez@lab.urosario.edu.co',NULL,1,0,1,'2026-02-16 16:05:45','Asignacion_Masiva','guillermo.fonseca'),(41,'SNLEN2026031',36,'QMA130019','Quinta de Mutis','Casa Vida Diaria','','Caso: 105081','gabriel.martinez@lab.urosario.edu.co',NULL,1,0,1,'2026-02-16 16:05:45','Asignacion_Masiva','guillermo.fonseca'),(42,'SNLEN2026032',36,'QMA130020','Quinta de Mutis','Casa Vida Diaria','','Caso: 105081','gabriel.martinez@lab.urosario.edu.co',NULL,1,0,1,'2026-02-16 16:05:45','Asignacion_Masiva','guillermo.fonseca'),(43,'AP2025002',55,'AP2025002',NULL,NULL,NULL,'OC: 2025-0891-OC','guillermo.fonseca',NULL,0,0,0,'2026-02-17 08:37:22','Alta','guillermo.fonseca'),(44,'SNLEN2026032',55,'LOTE:20260217084522-658',NULL,NULL,NULL,'Motivo Baja: Daño del equipo','Activos Fijos',NULL,0,0,0,'2026-02-17 08:45:22','Baja','guillermo.fonseca'),(45,'SNLEN2026032',55,'SNLEN2026032',NULL,NULL,NULL,'Reversión de baja administrativa por guillermo.fonseca','guillermo.fonseca',NULL,0,0,0,'2026-02-17 08:49:39','Alta','guillermo.fonseca'),(46,'SNHP2026001',55,'SNHP2026001',NULL,NULL,NULL,'OC: 2025-0123-OC','guillermo.fonseca',NULL,0,0,0,'2026-02-17 08:54:33','Alta','guillermo.fonseca'),(47,'SNHP2026002',55,'SNHP2026002',NULL,NULL,NULL,'OC: 2025-0123-OC','guillermo.fonseca',NULL,0,0,0,'2026-02-17 08:54:33','Alta','guillermo.fonseca'),(48,'SNHP2026003',55,'SNHP2026003',NULL,NULL,NULL,'OC: 2025-0123-OC','guillermo.fonseca',NULL,0,0,0,'2026-02-17 08:54:33','Alta','guillermo.fonseca'),(49,'AP2025002',43,'NTA130021','SEIC','GSB','Equipo nuevo con perifericos','Caso: 105083','carlos.morenog@lab.urosario.edu.co','juan.bustacara@lab.urosario.edu.co',0,0,1,'2026-02-17 08:59:18','Asignación','guillermo.fonseca'),(50,'SNHP2026001',18,'CNA130022',NULL,NULL,'Equipo nuevo con teclado y mouse','Caso: 105084','juan.bustacara@lab.urosario.edu.co','julio.moreno@lab.urosario.edu.co',1,1,1,'2026-02-17 09:02:25','Asignación','guillermo.fonseca'),(51,'SNHP2026003',3,'CNF130024',NULL,NULL,'Con Mouse','Caso: 105084','jairo.santos@lab.urosario.edu.co','carlos.morenog@lab.urosario.edu.co',1,1,1,'2026-02-17 09:08:19','Asignacion_Masiva','guillermo.fonseca'),(52,'SNHP2026002',3,'CNF130023',NULL,NULL,'Con Webcam','Caso: 105084','jairo.santos@lab.urosario.edu.co','carlos.morenog@lab.urosario.edu.co',1,1,1,'2026-02-17 09:08:19','Asignacion_Masiva','guillermo.fonseca'),(53,'SNLEN2026013',55,'SNLEN2026013',NULL,NULL,NULL,'Reversión de baja administrativa por guillermo.fonseca','guillermo.fonseca',NULL,0,0,0,'2026-02-17 09:15:49','Alta','guillermo.fonseca'),(54,'SNLEN2026013',55,'LOTE:20260217091639-348',NULL,NULL,NULL,'Motivo Baja: Robo o pérdida del equipo','Activos Fijos',NULL,0,0,0,'2026-02-17 09:16:39','Baja','guillermo.fonseca'),(55,'SNHP2026003',23,'QMF130024',NULL,NULL,'Equipo nuevo con teclado y mouse','Caso: 105085','carlos.morenog@lab.urosario.edu.co',NULL,1,1,1,'2026-02-17 10:23:15','Asignación','guillermo.fonseca'),(56,'SNHP2026003',2,'CNF130024',NULL,NULL,'Con Mouse','Caso: 105084','guillermo.fonseca@lab.urosario.edu.co',NULL,1,1,1,'2026-02-17 10:24:56','Asignacion_Masiva','guillermo.fonseca'),(57,'SNHP2026002',2,'CNF130023',NULL,NULL,'Con Webcam','Caso: 105084','guillermo.fonseca@lab.urosario.edu.co',NULL,1,1,1,'2026-02-17 10:24:56','Asignacion_Masiva','guillermo.fonseca'),(58,'SNLEN2026012',55,'LOTE:20260217102641-643',NULL,NULL,NULL,'Motivo Baja: Daño del equipo','Activos Fijos',NULL,0,0,0,'2026-02-17 10:26:41','Baja','guillermo.fonseca');
/*!40000 ALTER TABLE `bitacora` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `equipos`
--

DROP TABLE IF EXISTS `equipos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `equipos` (
  `id_equipo` int NOT NULL AUTO_INCREMENT COMMENT 'Identificador único interno (PK)',
  `serial` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Número de serie único de fábrica (UNIQUE). Llave natural para trazabilidad.',
  `placa_ur` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Placa patrimonial Universidad del Rosario (UNIQUE). Código de activo fijo.',
  `marca` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Fabricante del equipo (ej: HP, Lenovo, Dell)',
  `modelo` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Modelo específico del equipo (ej: EliteBook 845 G11)',
  `vida_util` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'Vida útil en años para depreciación contable (0 = sin definir)',
  `precio` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Valor de adquisición en USD para control patrimonial',
  `fecha_compra` date NOT NULL COMMENT 'Fecha de adquisición del equipo',
  `modalidad` enum('Propio','Leasing','Proyecto') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Forma de adquisición: Propio=comprado, Leasing=arrendado, Proyecto=financiado por proyecto específico',
  `estado_maestro` enum('Alta','Baja') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Alta' COMMENT 'Alta=disponible para asignación, Baja=fuera de servicio definitivo.',
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Timestamp de creación del registro',
  PRIMARY KEY (`id_equipo`),
  UNIQUE KEY `placa_ur` (`placa_ur`),
  UNIQUE KEY `serial` (`serial`),
  KEY `idx_equipos_id_estado` (`id_equipo` DESC,`estado_maestro`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Maestro de equipos de cómputo. Contiene información administrativa y financiera. estado_maestro controla disponibilidad para asignación.';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `equipos`
--

LOCK TABLES `equipos` WRITE;
/*!40000 ALTER TABLE `equipos` DISABLE KEYS */;
INSERT INTO `equipos` VALUES (1,'SNLEN2026012','130000','Lenovo','ThinkPad X1 Carbon Gen 13',5,8500000.00,'2025-03-20','Propio','Baja','2026-02-16 21:00:07'),(2,'SNLEN2026013','130001','Lenovo','ThinkPad X1 Carbon Gen 13',5,8500000.00,'2025-03-20','Propio','Baja','2026-02-16 21:01:22'),(3,'SNLEN2026014','130002','Lenovo','ThinkPad X1 Carbon Gen 13',5,8500000.00,'2025-03-20','Propio','Alta','2026-02-16 21:01:22'),(4,'SNLEN2026015','130003','Lenovo','ThinkPad X1 Carbon Gen 13',5,8500000.00,'2025-03-20','Propio','Alta','2026-02-16 21:01:22'),(5,'SNLEN2026016','130004','Lenovo','ThinkPad X1 Carbon Gen 13',5,8500000.00,'2025-03-20','Propio','Alta','2026-02-16 21:01:22'),(6,'SNLEN2026017','130005','Lenovo','ThinkPad X1 Carbon Gen 13',5,8500000.00,'2025-03-20','Propio','Alta','2026-02-16 21:01:22'),(7,'SNLEN2026018','130006','Lenovo','ThinkPad X1 Carbon Gen 13',5,8500000.00,'2025-03-20','Propio','Alta','2026-02-16 21:01:22'),(8,'SNLEN2026019','130007','Lenovo','ThinkPad X1 Carbon Gen 13',5,8500000.00,'2025-03-20','Propio','Alta','2026-02-16 21:01:22'),(9,'SNLEN2026020','130008','Lenovo','ThinkPad X1 Carbon Gen 13',5,8500000.00,'2025-03-20','Propio','Alta','2026-02-16 21:01:22'),(10,'SNLEN2026021','130009','Lenovo','ThinkPad X1 Carbon Gen 13',5,8500000.00,'2025-03-20','Propio','Alta','2026-02-16 21:01:22'),(11,'SNLEN2026022','130010','Lenovo','ThinkPad X1 Carbon Gen 13',5,8500000.00,'2025-03-20','Propio','Alta','2026-02-16 21:01:22'),(12,'SNLEN2026023','130011','Lenovo','ThinkPad X1 Carbon Gen 13',5,8500000.00,'2025-03-20','Propio','Alta','2026-02-16 21:01:22'),(13,'SNLEN2026024','130012','Lenovo','ThinkPad X1 Carbon Gen 13',5,8500000.00,'2025-03-20','Propio','Alta','2026-02-16 21:01:22'),(14,'SNLEN2026025','130013','Lenovo','ThinkPad X1 Carbon Gen 13',5,8500000.00,'2025-03-20','Propio','Alta','2026-02-16 21:01:22'),(15,'SNLEN2026026','130014','Lenovo','ThinkPad X1 Carbon Gen 13',5,8500000.00,'2025-03-20','Propio','Alta','2026-02-16 21:01:22'),(16,'SNLEN2026027','130015','Lenovo','ThinkPad X1 Carbon Gen 13',5,8500000.00,'2025-03-20','Propio','Alta','2026-02-16 21:01:22'),(17,'SNLEN2026028','130016','Lenovo','ThinkPad X1 Carbon Gen 13',5,8500000.00,'2025-03-20','Propio','Alta','2026-02-16 21:01:22'),(18,'SNLEN2026029','130017','Lenovo','ThinkPad X1 Carbon Gen 13',5,8500000.00,'2025-03-20','Propio','Alta','2026-02-16 21:01:22'),(19,'SNLEN2026030','130018','Lenovo','ThinkPad X1 Carbon Gen 13',5,8500000.00,'2025-03-20','Propio','Alta','2026-02-16 21:01:22'),(20,'SNLEN2026031','130019','Lenovo','ThinkPad X1 Carbon Gen 13',5,8500000.00,'2025-03-20','Propio','Alta','2026-02-16 21:01:22'),(21,'SNLEN2026032','130020','Lenovo','ThinkPad X1 Carbon Gen 13',5,8500000.00,'2025-03-20','Propio','Alta','2026-02-16 21:01:22'),(22,'AP2025002','130021','Apple','MacBook Air 15 M4',5,9500000.00,'2025-03-25','Leasing','Alta','2026-02-17 13:37:22'),(23,'SNHP2026001','130022','HP','EliteBook 845 G11',5,5750000.00,'2025-02-17','Leasing','Alta','2026-02-17 13:54:33'),(24,'SNHP2026002','130023','HP','EliteBook 845 G11',5,5750000.00,'2025-02-17','Leasing','Alta','2026-02-17 13:54:33'),(25,'SNHP2026003','130024','HP','EliteBook 845 G11',5,5750000.00,'2025-02-17','Leasing','Alta','2026-02-17 13:54:33');
/*!40000 ALTER TABLE `equipos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lugares`
--

DROP TABLE IF EXISTS `lugares`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lugares` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT 'Identificador único del lugar (PK)',
  `sede` enum('Centro','Quinta de Mutis','SEIC','Bodega tecnología') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Sede principal de la Universidad del Rosario',
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nombre específico del lugar (ej: "Oficina Piso 3", "Lab Biología")',
  `estado` tinyint(1) DEFAULT '1' COMMENT 'Soft delete: 1=Activo (disponible para nuevas asignaciones), 0=Inactivo (lugar clausurado pero se preserva por historial)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `sede` (`sede`,`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=56 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catálogo de ubicaciones físicas (edificios, pisos, oficinas). Soft delete: lugares inactivos se mantienen por trazabilidad histórica. DATOS MAESTROS - NO ELIMINAR.';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lugares`
--

LOCK TABLES `lugares` WRITE;
/*!40000 ALTER TABLE `lugares` DISABLE KEYS */;
INSERT INTO `lugares` VALUES (1,'Centro','Oficina Conecta UR Centro',1),(2,'Centro','Buhardilla',1),(3,'Centro','Cabal',1),(4,'Centro','Casa Reynolds',1),(5,'Centro','Casur 7ma',1),(6,'Centro','Casur salones',1),(7,'Centro','Claustro',1),(8,'Centro','Dávila',1),(9,'Centro','Edificio calle 12C',1),(10,'Centro','Edificio Fenalco',1),(11,'Centro','Edificio nuevo',1),(12,'Centro','El Tiempo',1),(13,'Centro','Jockey',1),(14,'Centro','Pedro Fermín',1),(15,'Centro','Santa Fe',1),(16,'Centro','Santander',1),(17,'Centro','Suramericana',1),(18,'Centro','Torre 1',1),(19,'Centro','Torre 2',1),(20,'Centro','Torre 3',1),(21,'Quinta de Mutis','Oficina Conecta UR QM',1),(22,'Quinta de Mutis','Bodega Neuros',1),(23,'Quinta de Mutis','Casa CEMA',1),(24,'Quinta de Mutis','Casa Ciencias Naturales',1),(25,'Quinta de Mutis','Casa Contratistas',1),(26,'Quinta de Mutis','Casa CREA',1),(27,'Quinta de Mutis','Casa Genética',1),(28,'Quinta de Mutis','Casa Historia de la Medicina',1),(29,'Quinta de Mutis','Casa miscelánea',1),(30,'Quinta de Mutis','Casa Neveras',1),(31,'Quinta de Mutis','Casa posgrados',1),(32,'Quinta de Mutis','Casa Psicología',1),(33,'Quinta de Mutis','Casa Reforma Curricular',1),(34,'Quinta de Mutis','Casa Riveros',1),(35,'Quinta de Mutis','Casa Rosea',1),(36,'Quinta de Mutis','Casa Vida Diaria',1),(37,'Quinta de Mutis','Edificio administrativo',1),(38,'SEIC','Oficina Conecta UR SEIC',1),(39,'SEIC','Casa Rosarista',1),(40,'SEIC','Fase 3',1),(41,'SEIC','Fase 8',1),(42,'SEIC','FCI',1),(43,'SEIC','GSB',1),(44,'SEIC','Misi',1),(45,'SEIC','Módulo 1',1),(46,'SEIC','Módulo 2',1),(47,'SEIC','Módulo 3',1),(48,'SEIC','Módulo 4',1),(49,'SEIC','Módulo 5',1),(50,'SEIC','Módulo 6',1),(51,'SEIC','Módulo A',1),(52,'SEIC','Módulo B',1),(53,'SEIC','Módulo C',1),(54,'SEIC','Módulo D',1),(55,'Bodega tecnología','Bodega de Tecnología',1);
/*!40000 ALTER TABLE `lugares` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mantenimientos`
--

DROP TABLE IF EXISTS `mantenimientos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mantenimientos` (
  `id_mantenimiento` int NOT NULL AUTO_INCREMENT COMMENT 'Identificador único del mantenimiento (PK)',
  `serial_equipo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Serial del equipo (FK a equipos.serial)',
  `tipo_mantenimiento` enum('Preventivo','Correctivo','Garantía','Diagnóstico') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Preventivo=programado, Correctivo=por falla, Garantía=cubierto por fabricante, Diagnóstico=solo revisión',
  `diagnostico_falla` text COLLATE utf8mb4_unicode_ci COMMENT 'Descripción de la falla reportada inicialmente por el usuario',
  `trabajo_realizado` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Detalle técnico del trabajo ejecutado por el técnico',
  `cambio_repuestos` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Indica si hubo cambio de piezas (0=No, 1=Sí)',
  `detalle_repuestos` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Listado de repuestos reemplazados (solo si cambio_repuestos=1)',
  `ticket_mesa_ayuda` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Número de ticket/caso en sistema externo de mesa de ayuda',
  `fecha_mantenimiento` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Timestamp de ejecución del mantenimiento',
  `tecnico_responsable` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Correo del técnico que ejecutó el mantenimiento. Extraído de sesión LDAP.',
  PRIMARY KEY (`id_mantenimiento`),
  KEY `idx_mant_serial` (`serial_equipo`),
  KEY `idx_mant_fecha` (`fecha_mantenimiento`),
  KEY `fk_mant_tecnico` (`tecnico_responsable`),
  CONSTRAINT `fk_mant_equipo` FOREIGN KEY (`serial_equipo`) REFERENCES `equipos` (`serial`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registro de mantenimientos preventivos y correctivos. Requiere técnico registrado en usuarios_sistema (FK).';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mantenimientos`
--

LOCK TABLES `mantenimientos` WRITE;
/*!40000 ALTER TABLE `mantenimientos` DISABLE KEYS */;
/*!40000 ALTER TABLE `mantenimientos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `prestamos`
--

DROP TABLE IF EXISTS `prestamos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prestamos` (
  `id_prestamo` int NOT NULL AUTO_INCREMENT COMMENT 'Identificador único del préstamo (PK)',
  `serial_equipo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Serial del equipo prestado (FK a equipos.serial)',
  `responsable_ppal` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Correo del responsable original del equipo (quien presta)',
  `responsable_sec` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Correo de quien recibe el préstamo temporal (usuario AD)',
  `sede_prestamo` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Sede donde se utilizará el equipo durante el préstamo',
  `ubicacion_prestamo` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ubicación específica durante el préstamo',
  `fecha_inicio` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Timestamp de inicio del préstamo',
  `fecha_fin_estimada` date NOT NULL COMMENT 'Fecha límite estimada de devolución (genera alerta si se excede)',
  `fecha_devolucion` datetime DEFAULT NULL COMMENT 'Timestamp real de devolución (NULL mientras esté activo)',
  `estado_prestamo` enum('Activo','Devuelto','Vencido') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Activo' COMMENT 'Activo=equipo prestado, Devuelto=devuelto a tiempo, Vencido=no devuelto en fecha_fin_estimada (requiere seguimiento por soporte)',
  `observaciones` text COLLATE utf8mb4_unicode_ci COMMENT 'Notas adicionales: motivo del préstamo, accesorios incluidos, estado físico',
  `tecnico_asigna` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Correo del técnico que autoriza/registra el préstamo (sesión)',
  `tecnico_recibe` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Correo del técnico que recibe la devolución (NULL mientras esté activo)',
  PRIMARY KEY (`id_prestamo`),
  KEY `idx_prestamo_validacion` (`serial_equipo`,`estado_prestamo`),
  KEY `idx_prestamo_vencimiento` (`estado_prestamo`,`fecha_fin_estimada`),
  KEY `fk_prestamo_tecnico_asigna` (`tecnico_asigna`),
  CONSTRAINT `fk_prestamo_equipo` FOREIGN KEY (`serial_equipo`) REFERENCES `equipos` (`serial`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registro de préstamos temporales de equipos. Requiere técnico asignador registrado en usuarios_sistema (FK).';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `prestamos`
--

LOCK TABLES `prestamos` WRITE;
/*!40000 ALTER TABLE `prestamos` DISABLE KEYS */;
/*!40000 ALTER TABLE `prestamos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuarios_sistema`
--

DROP TABLE IF EXISTS `usuarios_sistema`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuarios_sistema` (
  `id_usuario` int NOT NULL AUTO_INCREMENT COMMENT 'Identificador único interno (PK)',
  `correo_ldap` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Correo corporativo (usuario de Active Directory). Llave natural única para autenticación.',
  `nombre_completo` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nombre completo del usuario (obtenido de LDAP cn)',
  `rol` enum('Administrador','Soporte','Auditor','Recursos') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Rol del sistema: Administrador=gestión completa, Soporte=asignaciones y mantenimientos, Auditor=solo lectura de reportes, Recursos=gestión de personal',
  `estado` enum('Activo','Inactivo') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Activo' COMMENT 'Activo=puede ingresar al sistema, Inactivo=bloqueado (mantiene trazabilidad histórica de sus acciones pasadas)',
  `fecha_registro` datetime DEFAULT CURRENT_TIMESTAMP COMMENT 'Timestamp de creación del usuario en el sistema',
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `correo_ldap` (`correo_ldap`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Usuarios con acceso al sistema. Autenticación vía LDAP/Active Directory. Ahora con FKs desde bitacora, mantenimientos y prestamos. DATOS MAESTROS - NO ELIMINAR.';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios_sistema`
--

LOCK TABLES `usuarios_sistema` WRITE;
/*!40000 ALTER TABLE `usuarios_sistema` DISABLE KEYS */;
INSERT INTO `usuarios_sistema` VALUES (1,'guillermo.fonseca','Guillermo Alexander Fonseca','Administrador','Activo','2026-02-09 15:32:01'),(2,'carlos.morenog','Carlos Moreno','Recursos','Activo','2026-02-10 08:26:05'),(4,'andres.gonzalezc','Andres Gonzalez','Auditor','Activo','2026-02-10 08:34:07'),(5,'addison.rodriguez','Addison Rodriguez','Soporte','Activo','2026-02-10 08:36:24');
/*!40000 ALTER TABLE `usuarios_sistema` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Temporary view structure for view `v_auditoria_completa`
--

DROP TABLE IF EXISTS `v_auditoria_completa`;
/*!50001 DROP VIEW IF EXISTS `v_auditoria_completa`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v_auditoria_completa` AS SELECT 
 1 AS `tipo_evento`,
 1 AS `fecha_hora`,
 1 AS `usuario`,
 1 AS `ip_origen`,
 1 AS `descripcion`,
 1 AS `serial_equipo`,
 1 AS `placa_ur`,
 1 AS `tabla_origen`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `v_equipos_validaciones_incompletas`
--

DROP TABLE IF EXISTS `v_equipos_validaciones_incompletas`;
/*!50001 DROP VIEW IF EXISTS `v_equipos_validaciones_incompletas`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v_equipos_validaciones_incompletas` AS SELECT 
 1 AS `serial`,
 1 AS `placa_ur`,
 1 AS `marca`,
 1 AS `modelo`,
 1 AS `correo_responsable`,
 1 AS `fecha_asignacion`,
 1 AS `dias_desde_asignacion`,
 1 AS `check_sccm`,
 1 AS `check_dlo`,
 1 AS `check_antivirus`,
 1 AS `validacion_pendiente_1`,
 1 AS `validacion_pendiente_2`,
 1 AS `validacion_pendiente_3`,
 1 AS `total_validaciones_pendientes`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `v_estado_actual_equipos`
--

DROP TABLE IF EXISTS `v_estado_actual_equipos`;
/*!50001 DROP VIEW IF EXISTS `v_estado_actual_equipos`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v_estado_actual_equipos` AS SELECT 
 1 AS `id_equipo`,
 1 AS `serial`,
 1 AS `placa_ur`,
 1 AS `marca`,
 1 AS `modelo`,
 1 AS `modalidad`,
 1 AS `estado_maestro`,
 1 AS `precio`,
 1 AS `vida_util`,
 1 AS `fecha_compra`,
 1 AS `ultimo_evento`,
 1 AS `fecha_ultimo_evento`,
 1 AS `responsable_actual`,
 1 AS `sede_actual`,
 1 AS `ubicacion_actual`,
 1 AS `hostname_actual`,
 1 AS `id_lugar_actual`,
 1 AS `sede_lugar_actual`,
 1 AS `nombre_lugar_actual`,
 1 AS `dias_desde_compra`,
 1 AS `anos_desde_compra`,
 1 AS `estado_operativo`,
 1 AS `estado_depreciacion`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `v_resumen_movimientos_equipo`
--

DROP TABLE IF EXISTS `v_resumen_movimientos_equipo`;
/*!50001 DROP VIEW IF EXISTS `v_resumen_movimientos_equipo`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v_resumen_movimientos_equipo` AS SELECT 
 1 AS `serial`,
 1 AS `placa_ur`,
 1 AS `marca`,
 1 AS `modelo`,
 1 AS `estado_maestro`,
 1 AS `total_movimientos`,
 1 AS `total_altas`,
 1 AS `total_alistamientos`,
 1 AS `total_asignaciones`,
 1 AS `total_devoluciones`,
 1 AS `total_bajas`,
 1 AS `primer_movimiento`,
 1 AS `ultimo_movimiento`,
 1 AS `dias_con_actividad`,
 1 AS `total_responsables_diferentes`,
 1 AS `total_lugares_diferentes`,
 1 AS `dias_con_eventos`*/;
SET character_set_client = @saved_cs_client;

--
-- Final view structure for view `v_auditoria_completa`
--

/*!50001 DROP VIEW IF EXISTS `v_auditoria_completa`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_auditoria_completa` AS select 'LOGIN' AS `tipo_evento`,timestamp(`auditoria_acceso`.`fecha`,`auditoria_acceso`.`hora`) AS `fecha_hora`,`auditoria_acceso`.`usuario_ldap` AS `usuario`,`auditoria_acceso`.`ip_acceso` AS `ip_origen`,`auditoria_acceso`.`accion` AS `descripcion`,NULL AS `serial_equipo`,NULL AS `placa_ur`,'auditoria_acceso' AS `tabla_origen` from `auditoria_acceso` union all select `auditoria_cambios`.`tipo_accion` AS `tipo_evento`,`auditoria_cambios`.`fecha` AS `fecha_hora`,`auditoria_cambios`.`usuario_responsable` AS `usuario`,`auditoria_cambios`.`ip_origen` AS `ip_origen`,`auditoria_cambios`.`detalles` AS `descripcion`,`auditoria_cambios`.`referencia` AS `serial_equipo`,NULL AS `placa_ur`,'auditoria_cambios' AS `tabla_origen` from `auditoria_cambios` union all select `b`.`tipo_evento` AS `tipo_evento`,`b`.`fecha_evento` AS `fecha_hora`,coalesce(`b`.`tecnico_responsable`,'SYSTEM') AS `usuario`,NULL AS `ip_origen`,`b`.`desc_evento` AS `descripcion`,`b`.`serial_equipo` AS `serial_equipo`,`e`.`placa_ur` AS `placa_ur`,'bitacora' AS `tabla_origen` from (`bitacora` `b` left join `equipos` `e` on((`b`.`serial_equipo` = `e`.`serial`))) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_equipos_validaciones_incompletas`
--

/*!50001 DROP VIEW IF EXISTS `v_equipos_validaciones_incompletas`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_equipos_validaciones_incompletas` AS select `e`.`serial` AS `serial`,`e`.`placa_ur` AS `placa_ur`,`e`.`marca` AS `marca`,`e`.`modelo` AS `modelo`,`ult`.`correo_responsable` AS `correo_responsable`,`ult`.`fecha_evento` AS `fecha_asignacion`,(to_days(curdate()) - to_days(`ult`.`fecha_evento`)) AS `dias_desde_asignacion`,`ult`.`check_sccm` AS `check_sccm`,`ult`.`check_dlo` AS `check_dlo`,`ult`.`check_antivirus` AS `check_antivirus`,(case when (`ult`.`check_sccm` = 0) then 'SCCM pendiente' else NULL end) AS `validacion_pendiente_1`,(case when (`ult`.`check_dlo` = 0) then 'DLO pendiente' else NULL end) AS `validacion_pendiente_2`,(case when (`ult`.`check_antivirus` = 0) then 'Antivirus pendiente' else NULL end) AS `validacion_pendiente_3`,(3 - ((`ult`.`check_sccm` + `ult`.`check_dlo`) + `ult`.`check_antivirus`)) AS `total_validaciones_pendientes` from (`equipos` `e` join (select `b1`.`id_evento` AS `id_evento`,`b1`.`serial_equipo` AS `serial_equipo`,`b1`.`id_lugar` AS `id_lugar`,`b1`.`hostname` AS `hostname`,`b1`.`sede` AS `sede`,`b1`.`ubicacion` AS `ubicacion`,`b1`.`campo_adic1` AS `campo_adic1`,`b1`.`desc_evento` AS `desc_evento`,`b1`.`correo_responsable` AS `correo_responsable`,`b1`.`responsable_secundario` AS `responsable_secundario`,`b1`.`check_sccm` AS `check_sccm`,`b1`.`check_dlo` AS `check_dlo`,`b1`.`check_antivirus` AS `check_antivirus`,`b1`.`fecha_evento` AS `fecha_evento`,`b1`.`tipo_evento` AS `tipo_evento`,`b1`.`tecnico_responsable` AS `tecnico_responsable` from (`bitacora` `b1` join (select `bitacora`.`serial_equipo` AS `serial_equipo`,max(`bitacora`.`id_evento`) AS `max_evento` from `bitacora` where (`bitacora`.`tipo_evento` in ('Asignación','Asignacion_Masiva')) group by `bitacora`.`serial_equipo`) `b2` on(((`b1`.`serial_equipo` = `b2`.`serial_equipo`) and (`b1`.`id_evento` = `b2`.`max_evento`))))) `ult` on((`e`.`serial` = `ult`.`serial_equipo`))) where ((`e`.`estado_maestro` = 'Alta') and ((`ult`.`check_sccm` = 0) or (`ult`.`check_dlo` = 0) or (`ult`.`check_antivirus` = 0))) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_estado_actual_equipos`
--

/*!50001 DROP VIEW IF EXISTS `v_estado_actual_equipos`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_estado_actual_equipos` AS select `e`.`id_equipo` AS `id_equipo`,`e`.`serial` AS `serial`,`e`.`placa_ur` AS `placa_ur`,`e`.`marca` AS `marca`,`e`.`modelo` AS `modelo`,`e`.`modalidad` AS `modalidad`,`e`.`estado_maestro` AS `estado_maestro`,`e`.`precio` AS `precio`,`e`.`vida_util` AS `vida_util`,`e`.`fecha_compra` AS `fecha_compra`,`ult`.`tipo_evento` AS `ultimo_evento`,`ult`.`fecha_evento` AS `fecha_ultimo_evento`,`ult`.`correo_responsable` AS `responsable_actual`,`ult`.`sede` AS `sede_actual`,`ult`.`ubicacion` AS `ubicacion_actual`,`ult`.`hostname` AS `hostname_actual`,`l`.`id` AS `id_lugar_actual`,`l`.`sede` AS `sede_lugar_actual`,`l`.`nombre` AS `nombre_lugar_actual`,(to_days(curdate()) - to_days(`e`.`fecha_compra`)) AS `dias_desde_compra`,round(((to_days(curdate()) - to_days(`e`.`fecha_compra`)) / 365.25),1) AS `anos_desde_compra`,(case when (`e`.`estado_maestro` = 'Baja') then 'Dado de baja' when ((`ult`.`tipo_evento` = 'Alta') or (`ult`.`tipo_evento` = 'Alistamiento')) then 'En bodega' when ((`ult`.`tipo_evento` = 'Asignación') or (`ult`.`tipo_evento` = 'Asignacion_Masiva')) then 'Asignado' when (`ult`.`tipo_evento` = 'Devolución') then 'Devuelto a bodega' else 'Estado desconocido' end) AS `estado_operativo`,(case when ((`e`.`vida_util` > 0) and (((to_days(curdate()) - to_days(`e`.`fecha_compra`)) / 365.25) > `e`.`vida_util`)) then 'Vida útil cumplida' when ((`e`.`vida_util` > 0) and (((to_days(curdate()) - to_days(`e`.`fecha_compra`)) / 365.25) > (`e`.`vida_util` * 0.8))) then 'Próximo a cumplir vida útil' else 'Dentro de vida útil' end) AS `estado_depreciacion` from ((`equipos` `e` left join (select `b1`.`id_evento` AS `id_evento`,`b1`.`serial_equipo` AS `serial_equipo`,`b1`.`id_lugar` AS `id_lugar`,`b1`.`hostname` AS `hostname`,`b1`.`sede` AS `sede`,`b1`.`ubicacion` AS `ubicacion`,`b1`.`campo_adic1` AS `campo_adic1`,`b1`.`desc_evento` AS `desc_evento`,`b1`.`correo_responsable` AS `correo_responsable`,`b1`.`responsable_secundario` AS `responsable_secundario`,`b1`.`check_sccm` AS `check_sccm`,`b1`.`check_dlo` AS `check_dlo`,`b1`.`check_antivirus` AS `check_antivirus`,`b1`.`fecha_evento` AS `fecha_evento`,`b1`.`tipo_evento` AS `tipo_evento`,`b1`.`tecnico_responsable` AS `tecnico_responsable` from (`bitacora` `b1` join (select `bitacora`.`serial_equipo` AS `serial_equipo`,max(`bitacora`.`id_evento`) AS `max_evento` from `bitacora` group by `bitacora`.`serial_equipo`) `b2` on(((`b1`.`serial_equipo` = `b2`.`serial_equipo`) and (`b1`.`id_evento` = `b2`.`max_evento`))))) `ult` on((`e`.`serial` = `ult`.`serial_equipo`))) left join `lugares` `l` on((`ult`.`id_lugar` = `l`.`id`))) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_resumen_movimientos_equipo`
--

/*!50001 DROP VIEW IF EXISTS `v_resumen_movimientos_equipo`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_resumen_movimientos_equipo` AS select `e`.`serial` AS `serial`,`e`.`placa_ur` AS `placa_ur`,`e`.`marca` AS `marca`,`e`.`modelo` AS `modelo`,`e`.`estado_maestro` AS `estado_maestro`,count(`b`.`id_evento`) AS `total_movimientos`,sum((case when (`b`.`tipo_evento` = 'Alta') then 1 else 0 end)) AS `total_altas`,sum((case when (`b`.`tipo_evento` = 'Alistamiento') then 1 else 0 end)) AS `total_alistamientos`,sum((case when ((`b`.`tipo_evento` = 'Asignación') or (`b`.`tipo_evento` = 'Asignacion_Masiva')) then 1 else 0 end)) AS `total_asignaciones`,sum((case when (`b`.`tipo_evento` = 'Devolución') then 1 else 0 end)) AS `total_devoluciones`,sum((case when (`b`.`tipo_evento` = 'Baja') then 1 else 0 end)) AS `total_bajas`,min(`b`.`fecha_evento`) AS `primer_movimiento`,max(`b`.`fecha_evento`) AS `ultimo_movimiento`,(to_days(max(`b`.`fecha_evento`)) - to_days(min(`b`.`fecha_evento`))) AS `dias_con_actividad`,count(distinct `b`.`correo_responsable`) AS `total_responsables_diferentes`,count(distinct `b`.`id_lugar`) AS `total_lugares_diferentes`,count(distinct cast(`b`.`fecha_evento` as date)) AS `dias_con_eventos` from (`equipos` `e` left join `bitacora` `b` on((`e`.`serial` = `b`.`serial_equipo`))) group by `e`.`serial`,`e`.`placa_ur`,`e`.`marca`,`e`.`modelo`,`e`.`estado_maestro` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-02-17 10:45:01
