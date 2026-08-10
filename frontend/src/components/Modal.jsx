import { useEffect } from 'react';

export function Modal({ titre, onFermer, children, pied, large }) {
  useEffect(() => {
    const gerer = (e) => {
      if (e.key === 'Escape') onFermer?.();
    };
    document.addEventListener('keydown', gerer);
    return () => document.removeEventListener('keydown', gerer);
  }, [onFermer]);

  return (
    <div className="voile-modal" onMouseDown={(e) => e.target === e.currentTarget && onFermer?.()}>
      <div className={`modal${large ? ' modal-large' : ''}`}>
        <div className="modal-entete">
          <h3>{titre}</h3>
          <button type="button" className="modal-fermer" onClick={onFermer} aria-label="Fermer">
            ×
          </button>
        </div>
        <div className="modal-corps">{children}</div>
        {pied && <div className="modal-pied">{pied}</div>}
      </div>
    </div>
  );
}

export function ModalConfirmation({
  titre = 'Confirmation',
  message,
  libelleConfirmer = 'Confirmer',
  danger = false,
  enCours = false,
  onConfirmer,
  onAnnuler,
  children,
}) {
  return (
    <Modal
      titre={titre}
      onFermer={onAnnuler}
      pied={
        <>
          <button type="button" className="btn btn-secondaire" onClick={onAnnuler} disabled={enCours}>
            Annuler
          </button>
          <button
            type="button"
            className={`btn${danger ? ' btn-danger' : ''}`}
            onClick={onConfirmer}
            disabled={enCours}
          >
            {enCours ? 'Veuillez patienter…' : libelleConfirmer}
          </button>
        </>
      }
    >
      {message && <p>{message}</p>}
      {children}
    </Modal>
  );
}
