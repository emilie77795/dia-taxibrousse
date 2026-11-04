CREATE DATABASE dia_taxibrousse;
\c dia_taxibrousse

CREATE TABLE utilisateur (
    id SERIAL PRIMARY KEY,
    nom VARCHAR(255) NOT NULL,
    prenom VARCHAR(255) NOT NULL,
    telephone VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    lieu VARCHAR(255),
    role VARCHAR(50) NOT NULL,
    mot_de_passe VARCHAR(255) NOT NULL
);

CREATE TABLE chauffeur (
    id SERIAL PRIMARY KEY,
    nom VARCHAR(255) NOT NULL,
    contact VARCHAR(255) NOT NULL
);

CREATE TABLE type_vehicule (
    id SERIAL PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    image VARCHAR(255)
);

CREATE TABLE vehicule (
    id SERIAL PRIMARY KEY,
    matricule VARCHAR(50) NOT NULL UNIQUE,
    id_type_vehicule INTEGER NOT NULL REFERENCES type_vehicule(id),
    nombre_places INTEGER NOT NULL,
    id_chauffeur INTEGER REFERENCES chauffeur(id)
);

CREATE TABLE place (
    id SERIAL PRIMARY KEY,
    numero_place INTEGER NOT NULL,
    id_vehicule INTEGER NOT NULL REFERENCES vehicule(id),
    statut VARCHAR(50) NOT NULL DEFAULT 'disponible'
);

CREATE TABLE trajet (
    id SERIAL PRIMARY KEY,
    ville_depart VARCHAR(255) NOT NULL,
    ville_arrivee VARCHAR(255) NOT NULL,
    date DATE NOT NULL,
    horaire TIME NOT NULL,
    id_vehicule INTEGER NOT NULL REFERENCES vehicule(id)
);

CREATE TABLE tarif (
    id SERIAL PRIMARY KEY,
    id_trajet INTEGER NOT NULL REFERENCES trajet(id),
    id_type_vehicule INTEGER NOT NULL REFERENCES type_vehicule(id),
    prix DECIMAL(10,2) NOT NULL
);

CREATE TABLE reservation (
    id SERIAL PRIMARY KEY,
    numero_ticket VARCHAR(50) NOT NULL UNIQUE,
    id_utilisateur INTEGER NOT NULL REFERENCES utilisateur(id),
    id_trajet INTEGER NOT NULL REFERENCES trajet(id),
    date DATE NOT NULL,
    montant_total DECIMAL(10,2) NOT NULL,
    statut VARCHAR(50) NOT NULL DEFAULT 'confirme'
);

CREATE TABLE reservation_place (
    id SERIAL PRIMARY KEY,
    id_reservation INTEGER NOT NULL REFERENCES reservation(id),
    id_place INTEGER NOT NULL REFERENCES place(id)
);

CREATE TABLE paiement (
    id SERIAL PRIMARY KEY,
    id_reservation INTEGER NOT NULL REFERENCES reservation(id),
    numero_telephone VARCHAR(50),
    operateur VARCHAR(50),
    montant DECIMAL(10,2) NOT NULL,
    statut VARCHAR(50) NOT NULL DEFAULT 'impaye'
);

CREATE TABLE notification (
    id SERIAL PRIMARY KEY,
    type VARCHAR(100) NOT NULL,
    id_utilisateur INTEGER NOT NULL REFERENCES utilisateur(id),
    statut VARCHAR(50) NOT NULL DEFAULT 'non_lu'
);