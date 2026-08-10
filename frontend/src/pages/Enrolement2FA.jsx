import { useNavigate } from 'react-router-dom';
import Enrolement2FABloc from '../components/Enrolement2FABloc';
import { useAuth } from '../AuthContext';

export default function Enrolement2FA() {
  const { recharger, deconnecter } = useAuth();
  const naviguer = useNavigate();

  return (
    <div className="page-publique">
      <div className="carte-publique" style={{ maxWidth: 520 }}>
        <h1>Activation de la double authentification</h1>
        <p className="sous-titre">
          Votre rôle exige la double authentification. Vous devez l'activer avant de continuer.
        </p>
        <Enrolement2FABloc
          onTermine={async () => {
            await recharger();
            naviguer('/');
          }}
        />
        <div className="liens-publics">
          <button type="button" className="btn-lien" onClick={deconnecter}>
            Se déconnecter
          </button>
        </div>
      </div>
    </div>
  );
}
