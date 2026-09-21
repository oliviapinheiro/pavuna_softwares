# Pavuna Softwares

Sistema de controle de turmas e instrutores com login, perfis de acesso,
grade semanal, consultas, relatórios e cadastro de usuários.

## Instalação local

1. Copie a pasta para o diretório público do Apache, por exemplo:
   `C:\xampp\htdocs\pavuna_softwares`.
2. No phpMyAdmin, importe **somente** `database.sql`.
3. Copie `config.local.php.example` para `config.local.php`.
4. Ajuste nesse arquivo o host, a porta, o usuário e a senha do MySQL.
5. Abra `http://localhost/pavuna_softwares/cadastro.html` e crie o primeiro
   administrador.

O arquivo `database.sql` substitui os arquivos de instalação e migração
anteriores. O sistema usa exclusivamente as tabelas `usuarios`, `turmas`,
`aulas`, `matriculas` e `redefinicoes_senha`.

## Erro “Access denied” no MySQL

Esse erro normalmente é causado por usuário ou senha incorretos, não pelo
Apache. A aplicação não possui mais senha gravada no código.

- XAMPP costuma usar `root` sem senha por padrão; nesse caso deixe
  `PAVUNA_DB_PASS` vazio.
- A porta deve ser a porta do MySQL, não a porta do Apache. O exemplo mantém
  `3307`, que era a configuração recebida; se o MySQL estiver em `3306`, troque
  `PAVUNA_DB_PORT`.
- Se o MySQL tiver senha, informe-a somente em `config.local.php` ou nas
  variáveis de ambiente `PAVUNA_DB_*`.
- Não use `localhost` se a instalação estiver resolvendo para outro serviço;
  `127.0.0.1` evita essa ambiguidade em instalações locais.

## Perfis e permissões

| Perfil | Pode |
| --- | --- |
| Coordenador | Consultar tudo, cadastrar instrutores e alunos, desativar, reativar, excluir e gerar relatórios |
| Instrutor | Consultar grade, instrutores, alunos e relatórios; cadastrar alunos |
| Aluno | Consultar a grade e alterar a própria senha |

As permissões são verificadas no servidor. Esconder uma aba no navegador não
é usado como mecanismo de segurança.

## Segurança e validação

- Consultas ao banco usam PDO com prepared statements e emulação desativada.
- Senhas são armazenadas com `password_hash` e verificadas com
  `password_verify`.
- Credenciais do banco ficam fora do código publicado.
- Sessões usam modo estrito, cookie HTTP-only e rotação após login.
- Operações autenticadas de alteração exigem token CSRF.
- Nomes, e-mails, senhas, IDs, turmas, uploads e duplicidades são validados no
  servidor.
- Erros detalhados vão para o log do servidor; o navegador recebe mensagens
  seguras e úteis.
- A pasta `storage` bloqueia acesso direto aos links de redefinição de senha.

## Organização

As entradas públicas (`index.php`, `api/` e `auth/`) funcionam como
controladores finos. `config.php` centraliza conexão, sessão, validações,
respostas e CSRF; `includes/guard.php` concentra autenticação e autorização.
O `index.php` mantém a apresentação HTML e as ações da tela principal.

## Acessibilidade

O site mantém textos alternativos, foco visível, navegação por teclado,
link para pular ao conteúdo, anúncios para leitores de tela, tabelas com
legenda e cabeçalhos, rótulos associados e mensagens de erro.

## E-mail de redefinição

Em ambiente local, `PAVUNA_MAIL_LOG_DEV=true` grava o link em
`storage/emails.log`, além de tentar enviar o e-mail. Em produção, configure
um remetente real e desative o log de desenvolvimento.