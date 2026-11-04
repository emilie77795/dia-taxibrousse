-- 1. Vider toutes les tables liées, SAUF utilisateur
TRUNCATE TABLE
    notification,
    paiement,
    reservation_place,
    reservation,
    tarif,
    trajet,
    place,
    vehicule,
    type_vehicule,
    chauffeur
RESTART IDENTITY CASCADE;

DELETE FROM utilisateur
WHERE role != 'administrateur';

SELECT setval(
    pg_get_serial_sequence('utilisateur', 'id'),
    COALESCE((SELECT MAX(id) FROM utilisateur), 1)
);

 