<?php

namespace App\Services;

/**
 * Emissione rifiutata perché serve «Rinnova lo stesso» (quota in ritardo o
 * socio da dichiarare decaduto): la finestra la mostra comunque, anche se la
 * data scelta a mano è di un altro anno rispetto a quella proposta.
 */
class TesseraSenzaQuota extends \RuntimeException
{
}
