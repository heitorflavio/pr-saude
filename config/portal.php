<?php

return [
    // M-7: hash Argon2id válido para manter o custo de CPF inexistente igual ao de
    // senha errada. O texto de origem não é credencial e o hash pode ser público.
    'dummy_hash' => '$argon2id$v=19$m=65536,t=4,p=1$Wkl2NlJYVGhGSEs1MTRMZQ$hnx5gFhc3oTXe5gvLr39Qqsq8K8fqX7VWPgrWrLBOwA',
    'senha_provisoria_horas' => 72,
    'acesso_apos_alta_dias' => 30,
];
