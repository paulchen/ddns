-- phpMyAdmin SQL Dump
-- version 4.0.4.2
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Erstellungszeit: 31. Jul 2013 um 16:35
-- Server Version: 5.5.31-0+wheezy1
-- PHP-Version: 5.4.4-14+deb7u3

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

--
-- Datenbank: `ddns`
--
CREATE DATABASE IF NOT EXISTS `ddns` DEFAULT CHARACTER SET latin1 COLLATE latin1_swedish_ci;
USE `ddns`;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `accounts`
--

CREATE TABLE IF NOT EXISTS `accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` text NOT NULL,
  `password` text NOT NULL,
  `active` tinyint(4) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `current`
--

CREATE TABLE IF NOT EXISTS `current` (
  `host` int(11) NOT NULL,
  `ip` text NOT NULL,
  `ip6` text NOT NULL,
  PRIMARY KEY (`host`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `hosts`
--

CREATE TABLE IF NOT EXISTS `hosts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `updates`
--

CREATE TABLE IF NOT EXISTS `updates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `host` int(11) NOT NULL,
  `user` int(11) NOT NULL,
  `source_ip` text NOT NULL,
  `new_ip` text NOT NULL,
  `new_ip6` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `host` (`host`,`user`),
  KEY `user` (`user`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `update_dependency`
--

CREATE TABLE `update_dependency` (
  `id` int(11) NOT NULL,
  `from` int(11) NOT NULL,
  `to` int(11) NOT NULL,
  `ipv4` tinyint(1) NOT NULL DEFAULT 0,
  `ipv6` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

--
-- Indizes der exportierten Tabellen
--

--
-- Indizes für die Tabelle `update_dependency`
--
ALTER TABLE `update_dependency`
  ADD PRIMARY KEY (`id`),
  ADD KEY `from` (`from`),
  ADD KEY `to` (`to`);

--
-- AUTO_INCREMENT für exportierte Tabellen
--

--
-- AUTO_INCREMENT für Tabelle `update_dependency`
--
ALTER TABLE `update_dependency`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints der exportierten Tabellen
--

--
-- Constraints der Tabelle `current`
--
ALTER TABLE `current`
  ADD CONSTRAINT `current_ibfk_1` FOREIGN KEY (`host`) REFERENCES `hosts` (`id`);

--
-- Constraints der Tabelle `updates`
--
ALTER TABLE `updates`
  ADD CONSTRAINT `updates_ibfk_1` FOREIGN KEY (`host`) REFERENCES `hosts` (`id`),
  ADD CONSTRAINT `updates_ibfk_2` FOREIGN KEY (`user`) REFERENCES `accounts` (`id`);

--
-- Constraints der Tabelle `update_dependency`
--
ALTER TABLE `update_dependency`
  ADD CONSTRAINT `update_dependency_ibfk_1` FOREIGN KEY (`from`) REFERENCES `hosts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `update_dependency_ibfk_2` FOREIGN KEY (`to`) REFERENCES `hosts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

