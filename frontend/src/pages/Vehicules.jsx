import ListePage, { BadgeActif } from '../components/ListePage';

const SOURCE_MARQUES = '/api/marques?actif=true&par_page=100';
const SOURCE_SERVICES = '/api/services?actif=true&par_page=100';
const SOURCE_SITES = '/api/sites?actif=true&par_page=100';
const SOURCE_BENEFICIAIRES = '/api/beneficiaires?actif=true&par_page=100';

const OPTIONS_TYPE_VEHICULE = ['Voiture', 'Utilitaire', 'Camion', '4x4', 'Autre'].map((v) => ({
  valeur: v,
  libelle: v,
}));
const OPTIONS_CARBURANT = ['Gasoil', 'Essence', 'Hybride', 'Électrique'].map((v) => ({
  valeur: v,
  libelle: v,
}));
const OPTIONS_STATUT = ['Actif', 'En réparation', 'Réformé'].map((v) => ({ valeur: v, libelle: v }));

const CHAMPS = [
  { nom: 'immatriculation', libelle: 'Immatriculation', requis: true },
  { nom: 'marque_id', libelle: 'Marque', type: 'select', source: SOURCE_MARQUES },
  { nom: 'modele', libelle: 'Modèle' },
  { nom: 'type_vehicule', libelle: 'Type de véhicule', type: 'select', options: OPTIONS_TYPE_VEHICULE },
  { nom: 'type_carburant', libelle: 'Type de carburant', type: 'select', options: OPTIONS_CARBURANT },
  { nom: 'service_id', libelle: 'Service', type: 'select', source: SOURCE_SERVICES },
  { nom: 'site_id', libelle: 'Site', type: 'select', source: SOURCE_SITES },
  { nom: 'conducteur_id', libelle: 'Conducteur', type: 'select', source: SOURCE_BENEFICIAIRES },
  { nom: 'plafond_mensuel', libelle: 'Plafond mensuel (DH)', type: 'nombre' },
  { nom: 'statut', libelle: 'Statut', type: 'select', options: OPTIONS_STATUT },
  { nom: 'date_mise_en_service', libelle: 'Date de mise en service', type: 'date' },
  { nom: 'observation', libelle: 'Observation', type: 'textarea', large: true },
];

const COLONNES = [
  { cle: 'immatriculation', libelle: 'Immatriculation', tri: true },
  { cle: 'marque', libelle: 'Marque', rendu: (l) => l.marque?.libelle ?? '—' },
  { cle: 'modele', libelle: 'Modèle', tri: true },
  { cle: 'type_vehicule', libelle: 'Type' },
  { cle: 'type_carburant', libelle: 'Carburant' },
  { cle: 'service', libelle: 'Service', rendu: (l) => l.service?.libelle ?? '—' },
  { cle: 'site', libelle: 'Site', rendu: (l) => l.site?.libelle ?? '—' },
  { cle: 'conducteur', libelle: 'Conducteur', rendu: (l) => l.conducteur?.nom_complet ?? '—' },
  { cle: 'plafond_mensuel', libelle: 'Plafond mensuel', tri: true },
  {
    cle: 'statut',
    libelle: 'Statut',
    rendu: (l) => <span className="badge badge-bleu">{l.statut ?? '—'}</span>,
  },
  { cle: 'actif', libelle: 'État', rendu: (l) => <BadgeActif actif={l.actif !== false} /> },
];

const FILTRES = [
  { nom: 'marque_id', libelle: 'Marque', source: SOURCE_MARQUES },
  { nom: 'service_id', libelle: 'Service', source: SOURCE_SERVICES },
  { nom: 'site_id', libelle: 'Site', source: SOURCE_SITES },
  { nom: 'statut', libelle: 'Statut', options: OPTIONS_STATUT },
  { nom: 'type_carburant', libelle: 'Carburant', options: OPTIONS_CARBURANT },
  {
    nom: 'actif',
    libelle: 'État',
    options: [
      { valeur: 'true', libelle: 'Actif' },
      { valeur: 'false', libelle: 'Inactif' },
    ],
  },
];

export default function Vehicules() {
  return (
    <ListePage
      titre="Véhicules"
      ressource="vehicules"
      domaine="vehicule"
      colonnes={COLONNES}
      filtres={FILTRES}
      champs={CHAMPS}
      similarite
      libelleCreation="Nouveau véhicule"
      preparerFormulaire={(l) => ({
        immatriculation: l.immatriculation,
        marque_id: l.marque_id ?? l.marque?.id ?? null,
        modele: l.modele,
        type_vehicule: l.type_vehicule,
        type_carburant: l.type_carburant,
        service_id: l.service_id ?? l.service?.id ?? null,
        site_id: l.site_id ?? l.site?.id ?? null,
        conducteur_id: l.conducteur_id ?? l.conducteur?.id ?? null,
        plafond_mensuel: l.plafond_mensuel,
        statut: l.statut,
        date_mise_en_service: l.date_mise_en_service,
        observation: l.observation,
      })}
    />
  );
}
