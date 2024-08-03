-- MySQL Script generated by MySQL Workbench
-- Sat Aug  3 02:36:06 2024
-- Model: New Model    Version: 1.0
-- MySQL Workbench Forward Engineering

SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- -----------------------------------------------------
-- Schema bd_asistencia_alumnos
-- -----------------------------------------------------

-- -----------------------------------------------------
-- Schema bd_asistencia_alumnos
-- -----------------------------------------------------
CREATE SCHEMA IF NOT EXISTS `bd_asistencia_alumnos` DEFAULT CHARACTER SET utf8 ;
USE `bd_asistencia_alumnos` ;

-- -----------------------------------------------------
-- Table `bd_asistencia_alumnos`.`persona`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `bd_asistencia_alumnos`.`persona` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `dni` VARCHAR(40) NOT NULL,
  `nombre` VARCHAR(255) NOT NULL,
  `apellido` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `personas_ux_dni` (`dni` ASC) VISIBLE,
  UNIQUE INDEX `personas_ux_email` (`email` ASC) VISIBLE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `bd_asistencia_alumnos`.`registro_asistencia`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `bd_asistencia_alumnos`.`registro_asistencia` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `fecha` DATE NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `registro_asistencia_ux_fecha` (`fecha` ASC) VISIBLE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `bd_asistencia_alumnos`.`asistencia_persona`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `bd_asistencia_alumnos`.`asistencia_persona` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `id_persona` BIGINT NOT NULL,
  `id_registro_asistencia` BIGINT NOT NULL,
  `asistencia` ENUM('presente', 'ausente', 'retraso') NULL,
  `observaciones` TEXT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `asistencia_persona_ux_persona_asist` (`id_persona` ASC, `id_registro_asistencia` ASC) VISIBLE,
  INDEX `fk_asistencia_persona_registro_asistencia1_idx` (`id_registro_asistencia` ASC) VISIBLE,
  CONSTRAINT `fk_asistencia_persona_persona`
    FOREIGN KEY (`id_persona`)
    REFERENCES `bd_asistencia_alumnos`.`persona` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_asistencia_persona_registro_asistencia1`
    FOREIGN KEY (`id_registro_asistencia`)
    REFERENCES `bd_asistencia_alumnos`.`registro_asistencia` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
