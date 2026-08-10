import { useCallback, useEffect, useRef, useState } from 'react';
import { NavLink, Outlet, useLocation } from 'react-router-dom';
import api, { surActiviteApi } from '../api';
import { useAuth } from '../AuthContext';
import { Modal } from './Modal';

const ENTREES_MENU = [
  { chemin: '/', libelle: 'Tableau de bord', permission: null },
  { chemin: '/vehicules', libelle: 'Véhicules', permission: 'vehicule.consulter' },
  { chemin: '/beneficiaires', libelle: 'Bénéficiaires', permission: 'beneficiaire.consulter' },
  { chemin: '/types-vignette', libelle: 'Types de vignette', permission: 'type_vignette.consulter' },
  { chemin: '/motifs-sortie', libelle: 'Motifs de sortie', permission: 'motif_sortie.consulter' },
  { chemin: '/fournisseurs', libelle: 'Fournisseurs', permission: 'fournisseur.consulter' },
  { chemin: '/exercices', libelle: 'Exercices', permission: 'exercice.consulter' },
  { chemin: '/services', libelle: 'Services', permission: 'service.consulter' },
  { chemin: '/sites', libelle: 'Sites', permission: 'site.consulter' },
  { chemin: '/import', libelle: 'Import', permission: 'referentiel.importer' },
  { chemin: '/utilisateurs', libelle: 'Utilisateurs', permission: 'utilisateur.consulter' },
  { chemin: '/audit', libelle: "Journal d'audit", permission: 'audit.consulter' },
  { chemin: '/parametres', libelle: 'Paramètres', permission: 'parametre.consulter' },
  { chemin: '/mon-compte', libelle: 'Mon compte', permission: null },
];

function formaterCompteARebours(secondes) {
  const m = Math.floor(secondes / 60);
  const s = secondes % 60;
  return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
}

export default function Layout() {
  const { utilisateur, session, peut, deconnecter } = useAuth();
  const [exerciceCourant, setExerciceCourant] = useState(null);
  const [secondesRestantes, setSecondesRestantes] = useState(null);
  const [avertissementVisible, setAvertissementVisible] = useState(false);
  const [menuOuvert, setMenuOuvert] = useState(false);
  const echeanceRef = useRef(null);
  const avertissementRejeteRef = useRef(false);
  const emplacement = useLocation();

  const inactiviteMinutes = session?.inactivite_minutes || 30;

  const reinitialiserMinuterie = useCallback(() => {
    echeanceRef.current = Date.now() + inactiviteMinutes * 60 * 1000;
    avertissementRejeteRef.current = false;
    setAvertissementVisible(false);
  }, [inactiviteMinutes]);

  // Réinitialise la minuterie à chaque réponse API réussie.
  useEffect(() => {
    reinitialiserMinuterie();
    surActiviteApi(reinitialiserMinuterie);
    return () => surActiviteApi(null);
  }, [reinitialiserMinuterie]);

  // Tic-tac de la minuterie.
  useEffect(() => {
    const minuterie = setInterval(() => {
      if (!echeanceRef.current) return;
      const restantes = Math.max(0, Math.round((echeanceRef.current - Date.now()) / 1000));
      setSecondesRestantes(restantes);
      if (restantes === 0) {
        echeanceRef.current = null;
        deconnecter();
      } else if (restantes <= 120 && !avertissementRejeteRef.current) {
        setAvertissementVisible(true);
      }
    }, 1000);
    return () => clearInterval(minuterie);
  }, [deconnecter]);

  // Exercice en cours.
  useEffect(() => {
    if (!peut('exercice.consulter')) return;
    (async () => {
      try {
        const reponse = await api.get('/api/exercices', { params: { statut: 'ouvert' } });
        setExerciceCourant(reponse.data?.data?.[0] || null);
      } catch {
        setExerciceCourant(null);
      }
    })();
  }, [peut]);

  // Referme le menu mobile à chaque navigation.
  useEffect(() => {
    setMenuOuvert(false);
  }, [emplacement.pathname]);

  const prolongerSession = async () => {
    try {
      await api.get('/api/auth/me');
      // La minuterie est réinitialisée par l'intercepteur de réponse.
    } catch {
      // L'intercepteur gère la redirection en cas de 401.
    }
  };

  const entreesVisibles = ENTREES_MENU.filter((e) => !e.permission || peut(e.permission));

  return (
    <div className="disposition">
      <aside className={`menu-lateral${menuOuvert ? ' ouvert' : ''}`}>
        <div className="menu-titre">Vignettes carburant</div>
        <nav className="menu-nav">
          {entreesVisibles.map((entree) => (
            <NavLink
              key={entree.chemin}
              to={entree.chemin}
              end={entree.chemin === '/'}
              className={({ isActive }) => (isActive ? 'actif' : '')}
            >
              {entree.libelle}
            </NavLink>
          ))}
        </nav>
      </aside>

      <div className="contenu-principal">
        <header className="bandeau">
          <div className="bandeau-infos">
            <button
              type="button"
              className="menu-bouton-mobile"
              onClick={() => setMenuOuvert((o) => !o)}
              aria-label="Ouvrir le menu"
            >
              ☰
            </button>
            <div>
              <div className="bandeau-utilisateur">{utilisateur?.nom_complet}</div>
              <div className="bandeau-role">{utilisateur?.role?.libelle}</div>
            </div>
            {exerciceCourant && (
              <span className="bandeau-exercice">
                Exercice en cours : {exerciceCourant.libelle || exerciceCourant.annee}
              </span>
            )}
          </div>
          <div className="bandeau-infos">
            {secondesRestantes !== null && (
              <span
                className={`bandeau-session${secondesRestantes <= 120 ? ' session-alerte' : ''}`}
                title="Temps restant avant expiration de la session"
              >
                Session : {formaterCompteARebours(secondesRestantes)}
              </span>
            )}
            <button type="button" className="btn btn-secondaire btn-petit" onClick={deconnecter}>
              Déconnexion
            </button>
          </div>
        </header>

        <main className="zone-page">
          <Outlet />
        </main>
      </div>

      {avertissementVisible && (
        <Modal
          titre="Votre session va expirer"
          onFermer={() => {
            avertissementRejeteRef.current = true;
            setAvertissementVisible(false);
          }}
          pied={
            <>
              <button type="button" className="btn btn-secondaire" onClick={deconnecter}>
                Se déconnecter
              </button>
              <button type="button" className="btn" onClick={prolongerSession}>
                Prolonger
              </button>
            </>
          }
        >
          <p>
            Votre session expirera dans {formaterCompteARebours(secondesRestantes || 0)} par manque
            d'activité. Souhaitez-vous la prolonger ?
          </p>
        </Modal>
      )}
    </div>
  );
}
