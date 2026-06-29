
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
DROP TABLE IF EXISTS `audit_trail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_trail` (
  `id` int NOT NULL AUTO_INCREMENT,
  `table_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `record_id` int NOT NULL,
  `action_type` enum('INSERT','UPDATE','DELETE','APPROVE','REJECT') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `field_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `old_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `new_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `modified_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `modified_date` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=404 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `butiran_oyd`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `butiran_oyd` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nama_oyd` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `kp_oyd` varchar(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `alamat_oyd1` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `alamat_oyd2` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `alamat_oyd3` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `poskod_oyd` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `bandar_oyd` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `negeri_oyd` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `notelefon_oyd` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email_oyd` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `umur_oyd` int DEFAULT NULL,
  `jantina_oyd` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `agama_oyd` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `agamaLain_oyd` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `oku_oyd` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `bangsa_oyd` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `etnik_oyd` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `createdBy_oyd` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `createdDate_oyd` datetime DEFAULT NULL,
  `modifiedBy_oyd` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `modifiedDate_oyd` datetime DEFAULT NULL,
  PRIMARY KEY (`id`,`kp_oyd`) USING BTREE,
  UNIQUE KEY `kp_oyd` (`kp_oyd`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `butiran_peguam_panel`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `butiran_peguam_panel` (
  `id` int NOT NULL AUTO_INCREMENT,
  `namaPeguam` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `kpBaru` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `kpLama` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `jantina` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `noTelBimbit` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `emelPeguam` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `kelulusanAkademik` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `tarikhDiterimaMasuk` date NOT NULL,
  `tahunPengalaman` varchar(5) COLLATE utf8mb4_general_ci NOT NULL,
  `bilanganKes` varchar(5) COLLATE utf8mb4_general_ci NOT NULL,
  `keteranganKes` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `category` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `checkbox_value` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `checkbox_value_status` varchar(2) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '0',
  `clpNumber` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `clpMula` date DEFAULT NULL,
  `clpAkhir` date DEFAULT NULL,
  `csoNumber1` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cso1Tauliah` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cso1Mula` date DEFAULT NULL,
  `cso1Akhir` date DEFAULT NULL,
  `csoNumber2` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cso2Tauliah` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cso2Mula` date DEFAULT NULL,
  `cso2Akhir` date DEFAULT NULL,
  `csoNumber3` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cso3Tauliah` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cso3Mula` date DEFAULT NULL,
  `cso3Akhir` date DEFAULT NULL,
  `lokasiBerguam1` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `lokasiBerguam1_status` varchar(2) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '0',
  `lokasiBerguam2` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `lokasiBerguam2_status` varchar(2) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '0',
  `lokasiBerguam3` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `lokasiBerguam3_status` varchar(2) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '0',
  `namaFirma` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `namaInsurans` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `noPolisi` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `amaunPerlindungan` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `polisiMula` date DEFAULT NULL,
  `polisiAkhir` date DEFAULT NULL,
  `alamatFirma1` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `alamatFirma2` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `alamatFirma3` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `poskodFirma` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `bandarFirma` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `negeriFirma` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `noTelFirma` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `noFaksFirma` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `namaBank` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `noAkaunBank` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `alamatBank1` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `alamatBank2` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `alamatBank3` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `poskodBank` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `bandarBank` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `negeriBank` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `permohonan_status` varchar(2) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '0',
  `pegawaiBertugas` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `butiran_peguam_panel_2`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `butiran_peguam_panel_2` (
  `id` int NOT NULL AUTO_INCREMENT,
  `namaPeguam` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `kpBaru` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `kpLama` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `jantina` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `noTelBimbit` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `emelPeguam` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `kelulusanAkademik` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `tarikhDiterimaMasuk` date DEFAULT NULL,
  `tarikhDiterimaMasukSyarie` date DEFAULT NULL,
  `tahunPengalaman` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `tahunPengalamanSyarie` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `bilanganKes` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `keteranganKes` varchar(2000) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `sokonganPengarah` varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '0-tidak sokong 1-sokong',
  `ulasan_sokonganPengarah` varchar(600) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tarikh_sokonganPengarah` datetime DEFAULT NULL,
  `permohonan_status` varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '0',
  `ulasan_keputusanKP` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tarikh_keputusanKP` datetime DEFAULT NULL,
  `tarikhMohon` datetime DEFAULT CURRENT_TIMESTAMP,
  `tarikhBatal` date DEFAULT NULL,
  `sebabBatal` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tarikhTidakDiluluskan` date DEFAULT NULL,
  `sebabTidakDiluluskan` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `kpBaru` (`kpBaru`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=643 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `forms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `forms` (
  `id` int NOT NULL AUTO_INCREMENT,
  `cawangan` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `tarikh_khidmat_nasihat` date DEFAULT NULL,
  `tarikh_permohonan` date DEFAULT NULL,
  `nama` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nokp` varchar(12) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `umur` int DEFAULT NULL,
  `jantina` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `agama` varchar(30) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `oku` varchar(5) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `bangsa` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `etnik` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `keputusan` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `kategori_kes2` varchar(30) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `taraf` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `diterima` varchar(10) COLLATE utf8mb4_general_ci NOT NULL,
  `reason` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `alasan` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `sumbangan` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nilai_sumbangan` int DEFAULT NULL,
  `kelulusan` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `keputusan_menteri` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `kaedah_penerimaan` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tarikh_perakuan` date DEFAULT NULL,
  `tarikh_pemakluman` date DEFAULT NULL,
  `tarikh_pemakluman_ditolak` date DEFAULT NULL,
  `kaedah_pemakluman` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tarikh_pemberitahuan_perakuan` date DEFAULT NULL,
  `no_fail` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `no_sistem` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nama_pegawai` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `jenis_oyd` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `kategori_kes` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `jenis_kategori` varchar(30) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `jenis_jenayah` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `jenis_kes` varchar(5) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status_pengantaraan` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tarikh_penugasan` date DEFAULT NULL,
  `pilihan` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `kaedah_sidang` varchar(15) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `lokasi_pihak_pertama` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `lokasi_pihak_kedua` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `lokasi_pegawai_pengantara` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tarikh_persetujuan` date DEFAULT NULL,
  `tarikh_persetujuan_pengantaraan` date DEFAULT NULL,
  `tarikh_sidang` date DEFAULT NULL,
  `status_sidang` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cara_selesai` varchar(40) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `kpi` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `agih_kepada` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status_agihan` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nama_pihak` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nama_responden` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nama_mahkamah` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tarikh_pemfailan_kes` date DEFAULT NULL,
  `no_mahkamah` varchar(30) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nama_pegawai_penyiasat` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `keputusan_kendali_kes` varchar(15) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tarikh_pemfailan` date DEFAULT NULL,
  `tarikh_perintah` date DEFAULT NULL,
  `tarikh_selesai` date DEFAULT NULL,
  `tarikh_perintah_bersih` date DEFAULT NULL,
  `tarikh_serahan_perintah` date DEFAULT NULL,
  `sebab_selesai` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `alasan_selesai` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `kos` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `kos_oyd` int DEFAULT NULL,
  `kos_pihak_lawan` int DEFAULT NULL,
  `tarikh_kos_selesai` date DEFAULT NULL,
  `tarikh_pemberitahuan_oyd` date DEFAULT NULL,
  `tarikh_pemberitahuan_mahkamah` date DEFAULT NULL,
  `tarikh_tutup_fail` date DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `nama_pegawai_yang_dapat_kes` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tarikh_penugasan_peguam_panel` date DEFAULT NULL,
  `sebab_Tidak_Diluluskan` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `sebab_menolak` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `agamaLain` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `alasan_kesilapan_no_fail` text COLLATE utf8mb4_general_ci,
  `alasan_pemindahan_fail` text COLLATE utf8mb4_general_ci,
  `didaftarkan_oleh` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `jenis_sumbangan` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `kategori_kes_borang` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nama_penjaga` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nokp_penjaga` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `sebab_tutup_fail` text COLLATE utf8mb4_general_ci,
  `tarikh_KPKemaskini` datetime DEFAULT NULL,
  `tarikh_daftar` date DEFAULT NULL,
  `tarikh_pengarahKemaskini` datetime DEFAULT NULL,
  `pengantaraan_kategori_kes` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `pembatalan_borang_1` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `setuju_pengantara` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_duplicate` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1013 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `laporan_kes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `laporan_kes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pihak_pihak` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `no_fail` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `no_kes` varchar(30) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nama_pegawai` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tarikh_sebutan` date DEFAULT NULL,
  `fakta_ringkas` text COLLATE utf8mb4_general_ci,
  `isu` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ringkasan` text COLLATE utf8mb4_general_ci,
  `status_kes` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `id_kes` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=57 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mahkamah_sivil`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mahkamah_sivil` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nama_mahkamah` varchar(70) COLLATE utf8mb4_general_ci NOT NULL,
  `negeri_mahkamah` varchar(70) COLLATE utf8mb4_general_ci NOT NULL,
  `lokaliti_mahkamah` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `jenis_mahkamah` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=231 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mahkamah_syariah`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mahkamah_syariah` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nama_mahkamah` varchar(70) COLLATE utf8mb4_general_ci NOT NULL,
  `negeri_mahkamah` varchar(70) COLLATE utf8mb4_general_ci NOT NULL,
  `lokaliti_mahkamah` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `jenis_mahkamah` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=231 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pegawai_jbg`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pegawai_jbg` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nama` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cawangan` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `jawatan` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `bahagian` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `jenis_pegawai` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status_aktif` varchar(2) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=222 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `peguam_panel`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `peguam_panel` (
  `nama_peguam` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `tarikh_penugasan_peguam_panel` date DEFAULT NULL,
  `kp_peguam` varchar(12) COLLATE utf8mb4_general_ci NOT NULL,
  `tel_peguam` varchar(15) COLLATE utf8mb4_general_ci NOT NULL,
  `emel_peguam` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `nama_firma` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `alamat_firma_1` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `alamat_firma_2` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `alamat_firma_3` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `poskod_firma` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `negeri_firma` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `tel_firma` varchar(50) COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `posters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `posters` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tajuk_poster` varchar(255) DEFAULT NULL,
  `details_poster` text,
  `status_poster` varchar(20) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `created_by` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_by` varchar(255) DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ref_cuti`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ref_cuti` (
  `id_cuti` int NOT NULL AUTO_INCREMENT,
  `nama_cuti` varchar(255) DEFAULT NULL,
  `tarikh_mula` date DEFAULT NULL,
  `tarikh_tamat` date DEFAULT NULL,
  `created` date DEFAULT NULL,
  `idnegeri` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_cuti`)
) ENGINE=InnoDB AUTO_INCREMENT=226 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ref_kes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ref_kes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_kes` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `jenis_kes` varchar(5) COLLATE utf8mb4_general_ci NOT NULL,
  `kategori_kes` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `deskripsi` varchar(500) COLLATE utf8mb4_general_ci NOT NULL,
  `aktif_kes` varchar(2) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tarikh_kuatkuasa` date DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=220 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ref_lokasi_berguam`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ref_lokasi_berguam` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nama` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ref_negeri`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ref_negeri` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nama` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `aktif` varchar(1) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'aktif=1\r\ntidak=0',
  `kategori` varchar(1) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sejarah_pegawai`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sejarah_pegawai` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_kes` int DEFAULT NULL,
  `nama_pegawai_lama` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tarikh_kemaskini` datetime DEFAULT NULL,
  `dikemaskini_oleh` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_kes` (`id_kes`),
  CONSTRAINT `sejarah_pegawai_ibfk_1` FOREIGN KEY (`id_kes`) REFERENCES `forms` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sejarah_peguam_panel`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sejarah_peguam_panel` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_kes` int DEFAULT NULL,
  `nama_pp_lama` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tarikh_penugasan` date DEFAULT NULL,
  `status` varchar(5) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `alasan` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `kp_pp_lama` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `modifiedBy` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `modifiedDate` datetime DEFAULT NULL,
  `status_agihan` varchar(2) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_kes` (`id_kes`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sejarah_sidang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sejarah_sidang` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_kes` int NOT NULL,
  `tarikh_sidang` date NOT NULL,
  `alasan_tangguh` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `dikemaskini_oleh` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `uploaded_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `uploaded_files` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nama` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `file_type` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=66 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

