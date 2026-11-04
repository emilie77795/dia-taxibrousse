-- =============================================================
-- SEED DATA - DIA TAXIBROUSSE
-- Données de référence (sans utilisateurs — créez-les via POST /api/register)
-- =============================================================

-- Types de véhicules
INSERT INTO type_vehicule (nom) VALUES
    ('Van'),
    ('Sprinter'),
    ('Mini bus');

-- Chauffeurs
INSERT INTO chauffeur (nom, contact) VALUES
    ('Rakoto Jean', '034 12 345 67'),
    ('Rasoa Marie', '033 98 765 43');

-- Véhicules
INSERT INTO vehicule (matricule, id_type_vehicule, nombre_places, id_chauffeur) VALUES
    ('1234 TBA',
        (SELECT id FROM type_vehicule WHERE nom = 'Van'), 8,
        (SELECT id FROM chauffeur WHERE nom = 'Rakoto Jean')),
    ('5678 TBB',
        (SELECT id FROM type_vehicule WHERE nom = 'Sprinter'), 15,
        (SELECT id FROM chauffeur WHERE nom = 'Rasoa Marie'));

-- Places du véhicule 1 (Van - 8 places)
INSERT INTO place (numero_place, id_vehicule, statut)
SELECT generate_series(1, 8),
       (SELECT id FROM vehicule WHERE matricule = '1234 TBA'),
       'disponible';

-- Places du véhicule 2 (Sprinter - 15 places)
INSERT INTO place (numero_place, id_vehicule, statut)
SELECT generate_series(1, 15),
       (SELECT id FROM vehicule WHERE matricule = '5678 TBB'),
       'disponible';

-- Trajets
INSERT INTO trajet (ville_depart, ville_arrivee, date, horaire, id_vehicule) VALUES
    ('Antananarivo', 'Toamasina', '2026-08-22', '06:00',
        (SELECT id FROM vehicule WHERE matricule = '1234 TBA')),
    ('Antananarivo', 'Toamasina', '2026-08-22', '20:00',
        (SELECT id FROM vehicule WHERE matricule = '5678 TBB'));

-- Tarifs
INSERT INTO tarif (id_trajet, id_type_vehicule, prix)
SELECT t.id, t2.id, 20000
FROM trajet t, type_vehicule t2
WHERE t.ville_depart = 'Antananarivo'
  AND t.horaire = '06:00'
  AND t2.nom = 'Van';

INSERT INTO tarif (id_trajet, id_type_vehicule, prix)
SELECT t.id, t2.id, 15000
FROM trajet t, type_vehicule t2
WHERE t.ville_depart = 'Antananarivo'
  AND t.horaire = '20:00'
  AND t2.nom = 'Sprinter';

-- =============================================================
-- ⚠️  UTILISATEURS : ne pas insérer ici
-- Créez vos comptes de test via l'API Symfony :
--   POST /api/register
--   Body JSON : { "nom":"Andria", "prenom":"Fanja", "telephone":"032 11 222 33",
--                 "email":"fanja.andria@example.com", "lieu":"Antananarivo",
--                 "role":"voyageur", "mot_de_passe":"votre_mot_de_passe" }
-- Le mot de passe sera hashé automatiquement par Symfony.
-- =============================================================