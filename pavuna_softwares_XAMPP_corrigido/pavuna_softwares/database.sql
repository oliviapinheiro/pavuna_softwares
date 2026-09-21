-- ============================================================
-- PAVUNA SOFTWARES — banco de dados oficial
-- Este é o único script de banco do projeto.
-- Faça backup antes de importar em uma instalação que já tenha dados.
-- ============================================================

CREATE DATABASE IF NOT EXISTS pavuna_softwares CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pavuna_softwares;

-- Usuários: coordenador, instrutor ou aluno, todos na mesma tabela
CREATE TABLE usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  senha VARCHAR(255) NOT NULL,
  tipo ENUM('coordenador','instrutor','aluno') NOT NULL,
  foto VARCHAR(255) DEFAULT NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE turmas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(30) NOT NULL,
  nome VARCHAR(150) NOT NULL
) ENGINE=InnoDB;

-- dia_semana: 0=Segunda, 1=Terça, 2=Quarta, 3=Quinta, 4=Sexta, 5=Sábado
CREATE TABLE aulas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  turma_id INT NOT NULL,
  dia_semana TINYINT NOT NULL,
  turno ENUM('manha','tarde','noite') NOT NULL,
  materia VARCHAR(150) NULL,              -- vazio = usa o nome da turma
  data_inicio DATE NULL,                  -- início da vigência (vazio = sem limite)
  data_fim DATE NULL,                     -- fim da vigência (vazio = sem limite)
  horas_aula DECIMAL(4,1) NOT NULL DEFAULT 4.0,  -- horas de cada encontro
  instrutor_id INT DEFAULT NULL,
  sala VARCHAR(30),
  status ENUM('confirmada','reposicao','cancelada') DEFAULT 'confirmada',
  FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE CASCADE,
  FOREIGN KEY (instrutor_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE matriculas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  aluno_id INT NOT NULL,
  turma_id INT NOT NULL,
  frequencia DECIMAL(5,2) DEFAULT 100.00,
  UNIQUE KEY uq_matricula_aluno_turma (aluno_id, turma_id),
  FOREIGN KEY (aluno_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- "Esqueci minha senha": guarda só o hash do token, com validade
CREATE TABLE redefinicoes_senha (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expira_em DATETIME NOT NULL,
  usado TINYINT(1) NOT NULL DEFAULT 0,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_token (token_hash),
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- Turmas ----------
INSERT INTO turmas (codigo, nome) VALUES
('DS-24', 'Dev. de Sistemas'),
('GEI-12', 'Gestão Industrial'),
('MOS-04', 'Modelagem de Sistemas'),
('MMA-03', 'Manut. Automóveis'),
('PCI-02', 'Assistente de Estilo'),
('FPF-01', 'Fundamentos de Física'),
('VES-05', 'Técnico em Vestuário'),
('MD-01', 'Manut. Máq. Pesadas'),
('FQ-01', 'Fundamentos de Química'),
('SST-01', 'Segurança do Trabalho');

-- ---------- Aulas (sem instrutor ainda: associe depois de cadastrar instrutores) ----------
INSERT INTO aulas (turma_id, dia_semana, turno, sala, status) VALUES
(1, 0, 'manha', '102 D', 'confirmada'),
(2, 0, 'tarde', '214 C', 'confirmada'),
(3, 0, 'noite', 'TF4',   'reposicao'),
(4, 1, 'manha', '119 A', 'confirmada'),
(5, 1, 'manha', '225 C', 'confirmada'),
(2, 1, 'tarde', '214 C', 'cancelada'),
(1, 2, 'manha', '102 D', 'confirmada'),
(6, 2, 'manha', '216 C', 'confirmada'),
(3, 2, 'tarde', 'TF4',   'confirmada'),
(7, 2, 'noite', '224 C', 'confirmada'),
(8, 3, 'manha', '107 B', 'confirmada'),
(9, 3, 'tarde', '210 C', 'confirmada'),
(3, 3, 'noite', 'TF4',   'reposicao'),
(10,4, 'tarde', 'Sala 15','confirmada'),
(3, 4, 'noite', 'TF4',   'confirmada'),
(1, 5, 'manha', '102 D', 'confirmada'),
(3, 5, 'tarde', 'TF4',   'confirmada');

-- Nenhum usuário é criado aqui de propósito: crie a primeira conta de
-- Coordenador pela própria tela de cadastro (cadastro.html), escolhendo
-- o tipo "Coordenador".

-- ============================================================
-- IMPORTAÇÃO DOS INSTRUTORES E ALUNOS QUE JÁ EXISTIAM NO data.js
-- Senha provisória de TODOS os importados abaixo: trocar123
-- (peça para cada um trocar a senha depois, criando uma conta nova
-- com o mesmo e-mail não é possível — futuramente dá pra adicionar
-- uma tela de "trocar senha"; por enquanto, edite pelo phpMyAdmin
-- ou use a opção "Alterar foto/nome" e recadastre a senha manualmente).
-- ============================================================

INSERT INTO usuarios (nome, email, senha, tipo) VALUES
('Marcos Vinícius',     'marcos.vinicius@pavuna.com',  '$2b$10$IcLEiHo0PsBUCd9Mw7otcuxmHO8Oac0Wpr6.KvPLkvbb9dFR7kuUy', 'instrutor'),
('Renata Alves',        'renata.alves@pavuna.com',     '$2b$10$IcLEiHo0PsBUCd9Mw7otcuxmHO8Oac0Wpr6.KvPLkvbb9dFR7kuUy', 'instrutor'),
('Cláudio Ely',         'claudio.ely@pavuna.com',      '$2b$10$IcLEiHo0PsBUCd9Mw7otcuxmHO8Oac0Wpr6.KvPLkvbb9dFR7kuUy', 'instrutor'),
('Josiane Melo',        'josiane.melo@pavuna.com',     '$2b$10$IcLEiHo0PsBUCd9Mw7otcuxmHO8Oac0Wpr6.KvPLkvbb9dFR7kuUy', 'instrutor'),
('Aélia Vasconcelos',   'aelia.vasconcelos@pavuna.com','$2b$10$IcLEiHo0PsBUCd9Mw7otcuxmHO8Oac0Wpr6.KvPLkvbb9dFR7kuUy', 'instrutor'),
('Guilherme Sá',        'guilherme.sa@pavuna.com',     '$2b$10$IcLEiHo0PsBUCd9Mw7otcuxmHO8Oac0Wpr6.KvPLkvbb9dFR7kuUy', 'instrutor'),
('Juliana Costa',       'juliana.costa@pavuna.com',    '$2b$10$IcLEiHo0PsBUCd9Mw7otcuxmHO8Oac0Wpr6.KvPLkvbb9dFR7kuUy', 'instrutor'),
('Paulo Enrique',       'paulo.enrique@pavuna.com',    '$2b$10$IcLEiHo0PsBUCd9Mw7otcuxmHO8Oac0Wpr6.KvPLkvbb9dFR7kuUy', 'instrutor'),
('Lucimar Moraes',      'lucimar.moraes@pavuna.com',   '$2b$10$IcLEiHo0PsBUCd9Mw7otcuxmHO8Oac0Wpr6.KvPLkvbb9dFR7kuUy', 'instrutor');

INSERT INTO usuarios (nome, email, senha, tipo) VALUES
('Olivia Pinheiro Matos',              'olivia.matos@aluno.pavuna.com',    '$2b$10$IcLEiHo0PsBUCd9Mw7otcuxmHO8Oac0Wpr6.KvPLkvbb9dFR7kuUy', 'aluno'),
('Samuel Mayrink Batista',             'samuel.batista@aluno.pavuna.com',  '$2b$10$IcLEiHo0PsBUCd9Mw7otcuxmHO8Oac0Wpr6.KvPLkvbb9dFR7kuUy', 'aluno'),
('Gabriel Lopes Anibal Costa',         'gabriel.costa@aluno.pavuna.com',   '$2b$10$IcLEiHo0PsBUCd9Mw7otcuxmHO8Oac0Wpr6.KvPLkvbb9dFR7kuUy', 'aluno'),
('Yuri Vieri Santana de Paula',        'yuri.paula@aluno.pavuna.com',      '$2b$10$IcLEiHo0PsBUCd9Mw7otcuxmHO8Oac0Wpr6.KvPLkvbb9dFR7kuUy', 'aluno'),
('Daniel Luigi Simões Campos',         'daniel.campos@aluno.pavuna.com',   '$2b$10$IcLEiHo0PsBUCd9Mw7otcuxmHO8Oac0Wpr6.KvPLkvbb9dFR7kuUy', 'aluno'),
('Manuella Gonçalves Soares',          'manuella.soares@aluno.pavuna.com', '$2b$10$IcLEiHo0PsBUCd9Mw7otcuxmHO8Oac0Wpr6.KvPLkvbb9dFR7kuUy', 'aluno'),
('Lucas Gonçalves Maximiano da Costa', 'lucas.costa@aluno.pavuna.com',     '$2b$10$IcLEiHo0PsBUCd9Mw7otcuxmHO8Oac0Wpr6.KvPLkvbb9dFR7kuUy', 'aluno'),
('Ana Luiza Dutra Moreira',            'ana.moreira@aluno.pavuna.com',     '$2b$10$IcLEiHo0PsBUCd9Mw7otcuxmHO8Oac0Wpr6.KvPLkvbb9dFR7kuUy', 'aluno'),
('Leandro Francisco Moreira Santos',   'leandro.santos@aluno.pavuna.com',  '$2b$10$IcLEiHo0PsBUCd9Mw7otcuxmHO8Oac0Wpr6.KvPLkvbb9dFR7kuUy', 'aluno'),
('Miguel Campos Mendes',               'miguel.mendes@aluno.pavuna.com',   '$2b$10$IcLEiHo0PsBUCd9Mw7otcuxmHO8Oac0Wpr6.KvPLkvbb9dFR7kuUy', 'aluno'),
('Leonardo Fernandes de Carvalho',     'leonardo.carvalho@aluno.pavuna.com','$2b$10$IcLEiHo0PsBUCd9Mw7otcuxmHO8Oac0Wpr6.KvPLkvbb9dFR7kuUy', 'aluno'),
('Guilherme Ferreira Marques',         'guilherme.marques@aluno.pavuna.com','$2b$10$IcLEiHo0PsBUCd9Mw7otcuxmHO8Oac0Wpr6.KvPLkvbb9dFR7kuUy', 'aluno');

-- Matrículas dos alunos importados (turma + frequência original do data.js)
INSERT INTO matriculas (aluno_id, turma_id, frequencia)
SELECT u.id, t.id, v.frequencia FROM (
  SELECT 'olivia.matos@aluno.pavuna.com' AS email, 'DS-24' AS codigo, 96 AS frequencia
  UNION ALL SELECT 'samuel.batista@aluno.pavuna.com', 'DS-24', 88
  UNION ALL SELECT 'gabriel.costa@aluno.pavuna.com', 'DS-24', 61
  UNION ALL SELECT 'yuri.paula@aluno.pavuna.com', 'GEI-12', 92
  UNION ALL SELECT 'daniel.campos@aluno.pavuna.com', 'GEI-12', 79
  UNION ALL SELECT 'manuella.soares@aluno.pavuna.com', 'MMA-03', 85
  UNION ALL SELECT 'lucas.costa@aluno.pavuna.com', 'MMA-03', 70
  UNION ALL SELECT 'ana.moreira@aluno.pavuna.com', 'PCI-02', 98
  UNION ALL SELECT 'leandro.santos@aluno.pavuna.com', 'VES-05', 55
  UNION ALL SELECT 'miguel.mendes@aluno.pavuna.com', 'MOS-04', 90
  UNION ALL SELECT 'leonardo.carvalho@aluno.pavuna.com', 'MOS-04', 83
  UNION ALL SELECT 'guilherme.marques@aluno.pavuna.com', 'SST-01', 100
) v
JOIN usuarios u ON u.email = v.email
JOIN turmas t ON t.codigo = v.codigo;



-- Vincular cada aula ao instrutor correspondente (mesma combinação turma+dia+turno+sala do data.js original)
UPDATE aulas a JOIN turmas t ON t.id = a.turma_id
SET a.instrutor_id = (SELECT id FROM usuarios WHERE email = 'marcos.vinicius@pavuna.com')
WHERE t.codigo = 'DS-24' AND a.dia_semana IN (0,2,5) AND a.turno = 'manha';

UPDATE aulas a JOIN turmas t ON t.id = a.turma_id
SET a.instrutor_id = (SELECT id FROM usuarios WHERE email = 'marcos.vinicius@pavuna.com')
WHERE t.codigo = 'MOS-04';

UPDATE aulas a JOIN turmas t ON t.id = a.turma_id
SET a.instrutor_id = (SELECT id FROM usuarios WHERE email = 'renata.alves@pavuna.com')
WHERE t.codigo = 'GEI-12';

UPDATE aulas a JOIN turmas t ON t.id = a.turma_id
SET a.instrutor_id = (SELECT id FROM usuarios WHERE email = 'claudio.ely@pavuna.com')
WHERE t.codigo = 'MMA-03';

UPDATE aulas a JOIN turmas t ON t.id = a.turma_id
SET a.instrutor_id = (SELECT id FROM usuarios WHERE email = 'josiane.melo@pavuna.com')
WHERE t.codigo = 'PCI-02';

UPDATE aulas a JOIN turmas t ON t.id = a.turma_id
SET a.instrutor_id = (SELECT id FROM usuarios WHERE email = 'aelia.vasconcelos@pavuna.com')
WHERE t.codigo = 'FPF-01';

UPDATE aulas a JOIN turmas t ON t.id = a.turma_id
SET a.instrutor_id = (SELECT id FROM usuarios WHERE email = 'lucimar.moraes@pavuna.com')
WHERE t.codigo = 'VES-05';

UPDATE aulas a JOIN turmas t ON t.id = a.turma_id
SET a.instrutor_id = (SELECT id FROM usuarios WHERE email = 'guilherme.sa@pavuna.com')
WHERE t.codigo = 'MD-01';

UPDATE aulas a JOIN turmas t ON t.id = a.turma_id
SET a.instrutor_id = (SELECT id FROM usuarios WHERE email = 'juliana.costa@pavuna.com')
WHERE t.codigo = 'FQ-01';

UPDATE aulas a JOIN turmas t ON t.id = a.turma_id
SET a.instrutor_id = (SELECT id FROM usuarios WHERE email = 'paulo.enrique@pavuna.com')
WHERE t.codigo = 'SST-01';
