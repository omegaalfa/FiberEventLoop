# 🤝 Contribuindo para FiberEventLoop

Obrigado por considerar contribuir para o FiberEventLoop! Este documento fornece orientações e instruções para contribuidores.

## 📋 Índice

- [Código de Conduta](#código-de-conduta)
- [Como Contribuir](#como-contribuir)
- [Processo de Pull Request](#processo-de-pull-request)
- [Diretrizes de Código](#diretrizes-de-código)
- [Commit Message](#commit-message)
- [Reportar Bugs](#reportar-bugs)
- [Sugerir Melhorias](#sugerir-melhorias)

---

## Código de Conduta

### Nossa Promessa

No interesse de promover um ambiente aberto e acolhedor, nós, como contribuidores e mantenedores, nos comprometemos a fazer com que a participação em nosso projeto e nossa comunidade seja uma experiência livre de assédio para todos.

### Nossos Padrões

Exemplos de comportamento que contribuem para criar um ambiente positivo incluem:

- Usar linguagem inclusiva e acolhedora
- Ser respeitoso com pontos de vista e experiências diferentes
- Aceitar crítica construtiva graciosamente
- Focar no que é melhor para a comunidade
- Mostrar empatia com outros membros da comunidade

Exemplos de comportamento inaceitável incluem:

- Uso de linguagem ou imagens sexualizadas
- Ataques pessoais
- Comentários depreciativos ou insultos
- Assédio público ou privado
- Publicar informações privadas de outras pessoas
- Conduta que pudesse ser razoavelmente considerada inadequada

---

## Como Contribuir

### 1. Fork o Repositório

```bash
# Clonar seu fork
git clone https://github.com/seu-usuario/FiberEventLoop.git
cd FiberEventLoop

# Adicionar upstream
git remote add upstream https://github.com/omegaalfa/FiberEventLoop.git
```

### 2. Criar uma Branch

```bash
# Atualize a main
git fetch upstream
git checkout main
git rebase upstream/main

# Crie uma feature branch
git checkout -b feature/sua-feature
# ou para bugs
git checkout -b fix/seu-bug
```

**Convenção de nomenclatura:**
- `feature/descrição` - Para novas features
- `fix/descrição` - Para correção de bugs
- `docs/descrição` - Para documentação
- `test/descrição` - Para testes
- `perf/descrição` - Para otimizações

### 3. Desenvolver Localmente

```bash
# Instalar dependências
composer install

# Executar testes
composer test

# Rodar com cobertura
composer test -- --coverage-text
```

### 4. Commit Local

```bash
# Fazer commit com mensagem descritiva
git commit -m "Add: nova funcionalidade"
```

### 5. Push e Pull Request

```bash
# Push para seu fork
git push origin feature/sua-feature

# Abra PR no GitHub
```

---

## Processo de Pull Request

### Checklist Antes de Submeter

- [ ] Atualizei a documentação (README, PHPDoc, etc)
- [ ] Adicionei testes para nova funcionalidade
- [ ] Todos os testes passam (`composer test`)
- [ ] Meu código segue PSR-12
- [ ] Não adicionei dependências externas sem necessidade
- [ ] Minhas commits têm mensagens claras

### Título do PR

Use formato claro:
```
[TYPE] Descrição curta (50 caracteres max)

Exemplos:
[Feature] Add async file reading support
[Fix] Correct timer precision issue
[Docs] Improve README examples
[Test] Add integration tests
[Perf] Optimize defer execution
```

### Descrição do PR

```markdown
## Descrição
Breve descrição do que foi feito.

## Tipo de Mudança
- [ ] Feature (adição não-breaking)
- [ ] Bug fix
- [ ] Breaking change (descrever em detalhes)
- [ ] Documentação

## Problemas Relacionados
Fixes #issue-number

## Como Testar
Passo a passo para testar a funcionalidade.

## Checklist
- [ ] Testes passam
- [ ] Documentação atualizada
- [ ] Sem dependências novas
```

### Reviews

Esperamos que PRs sejam revisados antes de merge. Crítica é feita no código, não na pessoa.

---

## Diretrizes de Código

### PSR-12 Compliance

O projeto segue [PSR-12](https://www.php-fig.org/psr/psr-12/):

```php
<?php

declare(strict_types=1);

namespace Omegaalfa\FiberEventLoop;

/**
 * Descrição breve da classe
 * 
 * Descrição mais detalhada se necessária.
 * 
 * @package Omegaalfa\FiberEventLoop
 */
class Example
{
    /**
     * Descrição breve do método
     * 
     * Descrição detalhada.
     * 
     * @param int $param1 Descrição do parâmetro
     * @param string $param2 Descrição
     * 
     * @return bool Descrição do retorno
     * 
     * @example
     * ```php
     * $example = new Example();
     * $result = $example->method(10, 'test');
     * ```
     */
    public function method(int $param1, string $param2): bool
    {
        // Implementação
        return true;
    }
}
```

### Tipo de Dados

Use type hints sempre:

```php
// ✅ Correto
public function process(string $data): array
{
    // ...
}

// ❌ Incorreto
public function process($data)
{
    // ...
}
```

### Nomes Significativos

```php
// ✅ Bom
$activeConnections = [];
$maxRetries = 3;

// ❌ Ruim
$ac = [];
$mr = 3;
```

### Comprimento de Linha

Máximo 120 caracteres:

```php
// ✅ Correto
$result = $this->processLongMethodName(
    $parameter1,
    $parameter2,
    $parameter3
);

// ❌ Evitar
$result = $this->processLongMethodName($parameter1, $parameter2, $parameter3, $parameter4);
```

### Documentação PHPDoc

```php
/**
 * Descrição breve (uma linha)
 * 
 * Descrição mais detalhada com contexto e casos de uso.
 * Múltiplos parágrafos são permitidos.
 * 
 * @param type $name Descrição do parâmetro
 * @param type $name Descrição do parâmetro
 * 
 * @return type Descrição do retorno
 * 
 * @throws ExceptionType Quando esta exceção é lançada
 * 
 * @example
 * ```php
 * $obj = new MyClass();
 * $result = $obj->method('value');
 * echo $result; // Output: expected result
 * ```
 * 
 * @see ClassName::method() Related method
 * @see https://example.com Documentation link
 */
public function methodName(string $param): string
{
    // ...
}
```

---

## Commit Message

Seguir formato estruturado:

```
[TYPE] Descrição curta (50 chars)

Descrição mais detalhada, explicando:
- O que foi mudado
- Por que foi mudado
- Como funciona agora

Fixes #123
Relates to #124
```

### Tipos de Commit

- `Add` - Novo recurso
- `Fix` - Correção de bug
- `Docs` - Mudanças em documentação
- `Test` - Testes adicionados/melhorados
- `Perf` - Otimizações
- `Refactor` - Reorganização de código
- `Style` - Formatação/style (sem mudança funcional)
- `CI` - Mudanças em CI/CD

### Exemplos

```
Add: Async file reading support

Implement onReadFile() method for non-blocking file I/O.
Supports large files with configurable chunk size.
Integrates with Fiber-based event loop.

Fixes #42

Add: setOptimizationLevel() method

Allow runtime optimization switching between:
- latency: Minimum latency, maximum CPU
- throughput: Balanced
- efficient: CPU conservation
- balanced: Default

Relates to #45
```

---

## Reportar Bugs

### Antes de Reportar

- Verificar se o bug já foi reportado
- Tentar reproduzir com última versão
- Verificar documentação
- Checar Stack Overflow

### Template de Issue

```markdown
## Descrição do Bug
Descrição clara e concisa do problema.

## Para Reproduzir
Passo a passo:
1. ...
2. ...
3. ...

## Comportamento Esperado
O que deveria acontecer.

## Comportamento Atual
O que realmente acontece.

## Logs/Erro
```
// Cole mensagem de erro completa aqui
```

## Ambiente
- PHP Version: 8.2.0
- SO: Ubuntu 20.04
- Extensões: sockets

## Contexto Adicional
Qualquer informação relevante adicional.
```

---

## Sugerir Melhorias

### Template de Feature Request

```markdown
## Descrição da Melhoria
Descrição clara do que você gostaria adicionar.

## Problema Que Resolve
Qual problema esta feature resolve?

## Solução Proposta
Como você imagina esta feature funcionando?

## Alternativas Consideradas
Outras soluções exploradas?

## Contexto Adicional
Screenshots, links, exemplos de código.
```

---

## Desenvolvendo Localmente

### Instalação

```bash
# Clone seu fork
git clone https://github.com/seu-usuario/FiberEventLoop.git
cd FiberEventLoop

# Instale dependências
composer install
```

### Rodando Testes

```bash
# Todos os testes
composer test

# Testes específicos
composer test -- --filter "TimerManager"

# Com cobertura
composer test -- --coverage-html=coverage

# Performance
composer test -- --testsuite "Performance Tests"
```

### Verificar Qualidade

```bash
# PHPStan (type checking)
composer phpstan  # se configurado

# PHP-CS-Fixer
composer cs  # se configurado
```

---

## Documentação

### Adicionar Exemplos

Exemplos devem ser:
- Funcionais e testados
- Simples o suficiente para entender
- Documentados com comentários
- Inclusivos em README.md ou arquivo apropriado

```php
/**
 * @example
 * ```php
 * $loop = new FiberEventLoop();
 * 
 * // Exemplo funcional
 * $loop->after(function() {
 *     echo "Executa após 1 segundo\n";
 * }, 1.0);
 * 
 * $loop->run();
 * ```
 */
```

### Atualizar README

Se adicionar feature, adicione seção correspondente no README:

```markdown
#### `newMethod(type $param): type`

Descrição breve.

```php
// Exemplo de uso
$loop->newMethod($param);
```

**Parâmetros:**
- `$param`: Descrição

**Retorno:** Descrição
```

---

## Dúvidas?

- 💬 [GitHub Discussions](https://github.com/omegaalfa/FiberEventLoop/discussions)
- 🐛 [GitHub Issues](https://github.com/omegaalfa/FiberEventLoop/issues)
- 📧 Email: webdesenvolver.agenda@gmail.com

---

## Reconhecimento

Contribuidores serão reconhecidos em:
- README.md
- CHANGELOG.md
- Release notes

---

Obrigado por contribuir para FiberEventLoop! ❤️
