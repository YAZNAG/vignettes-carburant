import { useAuth } from '../AuthContext';

export default function TableauDeBord() {
  const { utilisateur } = useAuth();

  return (
    <div>
      <h1>Tableau de bord</h1>
      <div className="carte">
        <p>
          Bienvenue, <strong>{utilisateur?.nom_complet}</strong>.
        </p>
        <p style={{ color: 'var(--texte-secondaire)' }}>
          Les indicateurs et statistiques du tableau de bord seront disponibles dans le lot 2
          (gestion des dotations et des sorties de vignettes).
        </p>
      </div>
    </div>
  );
}
