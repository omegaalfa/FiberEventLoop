# 🔒 Política de Segurança

## Reportando Vulnerabilidades

Se você descobrir uma vulnerabilidade de segurança no FiberEventLoop, por favor não a reporte publicamente no GitHub Issues. Em vez disso, envie um email para:

**📧 security@example.com**

Por favor inclua:
- Descrição detalhada da vulnerabilidade
- Passos para reproduzir
- Potencial impacto
- Sugestão de fix (se houver)

Nós nos comprometemos a:
1. Reconhecer recebimento em 48 horas
2. Fornecer atualizações regularmente
3. Coordenar a divulgação
4. Dar crédito ao descobridor (se desejado)

---

## Suporte de Versão

| Versão | Status | Até |
|--------|--------|-----|
| 2.x | ✅ Ativa | TBD |
| 1.x | ⚠️ Manutenção | 2025-12-31 |
| < 1.0 | ❌ EOL | N/A |

- **Ativa**: Recebe features e security patches
- **Manutenção**: Recebe apenas security patches críticos
- **EOL**: Sem suporte

---

## Práticas de Segurança

### Código

✅ **Boas Práticas**
- Type hints em todos os parâmetros
- Validação de inputs
- Tratamento de exceções
- Logging de operações críticas
- Code review para PRs
- Testes de segurança

### Dependências

✅ **Política**
- Zero dependências externas (puro PHP)
- Verificação periódica com `composer audit`
- Updates rápidos de dependências dev (testes)
- Documentação de security advisories

### Releases

✅ **Processo**
- Testes de segurança antes do release
- Changelog de security fixes
- Notificação de usuários
- Patch releases para CVEs críticos

---

## Problemas de Segurança Conhecidos

Nenhum no momento.

---

## CVE Tracking

Este projeto é monitorado por:
- Packagist security advisories
- GitHub security alerts
- Dependabot

---

## Best Practices para Usuários

### 1. Atualizações Regulares

```bash
# Verificar atualizações
composer outdated

# Atualizar dependências seguras
composer update
```

### 2. Validação de Entrada

```php
// ✅ Bom: Valida e sanitiza
function handleClientData(string $data): void {
    $data = trim($data);
    
    if (!is_valid_protocol($data)) {
        throw new InvalidArgumentException('Invalid data');
    }
    
    // Processar data segura
}

// ❌ Ruim: Sem validação
function handleClientData(string $data): void {
    eval($data); // NUNCA!
}
```

### 3. Tratamento de Erros

```php
// ✅ Bom: Erros tratados
try {
    $loop->run();
} catch (Exception $e) {
    error_log($e->getMessage());
    // Retorna erro genérico ao cliente
    http_response_code(500);
}

// ❌ Ruim: Expõe detalhes
try {
    $loop->run();
} catch (Exception $e) {
    echo $e->getMessage(); // Expõe info interna!
    echo $e->getTraceAsString();
}
```

### 4. Gerenciamento de Memória

```php
// ✅ Bom: Limpa recursos
$loop = new FiberEventLoop();

try {
    // Código
    $loop->run();
} finally {
    // Cleanup
    fclose($server);
    unset($loop);
}

// ❌ Ruim: Leak de memória
$loop = new FiberEventLoop();
$loop->run();
// $loop não é liberado
```

### 5. Timeouts

```php
// ✅ Bom: Com timeout
$loop->after(function() {
    // Timeout protection
    $loop->stop();
}, 30.0); // 30 segundo timeout

$loop->run();

// ❌ Ruim: Sem timeout (DoS)
while (true) {
    $loop->run();
}
```

---

## OWASP Top 10 Mitigations

### A01:2021 – Broken Access Control
- ✅ Não armazena dados sensíveis em variáveis globais
- ✅ Suporta rate limiting em nível de aplicação

### A02:2021 – Cryptographic Failures
- ✅ Suporta TLS em streams (com `stream_context_create`)
- ⚠️ Não fornece criptografia built-in

### A03:2021 – Injection
- ✅ Use prepared statements em SQL
- ✅ Evite `eval()`, `exec()`, etc
- ⚠️ Responsabilidade da aplicação

### A04:2021 – Insecure Design
- ✅ Validação de entrada obrigatória
- ✅ Tratamento robusto de erros
- ✅ Timeouts configuráveis

### A05:2021 – Security Misconfiguration
- ✅ Padrões seguros por defecto
- ✅ Documentação de segurança
- ⚠️ Responsabilidade da aplicação

### A06:2021 – Vulnerable and Outdated Components
- ✅ Zero dependências externas
- ✅ Atualizações rápidas
- ✅ Composer audit

### A07:2021 – Identification and Authentication Failures
- ⚠️ Não fornece auth (responsabilidade da aplicação)
- ✅ Suporta rate limiting e throttling

### A08:2021 – Software and Data Integrity Failures
- ✅ Code signed com GPG (quando disponível)
- ✅ Checksum verificável

### A09:2021 – Logging and Monitoring Failures
- ✅ Error capturing integrado
- ✅ Métricas disponíveis
- ⚠️ Logging é responsabilidade da aplicação

### A10:2021 – Server-Side Request Forgery
- ⚠️ Responsabilidade da aplicação
- ✅ Valide URLs antes de usar

---

## Security Headers (para apps usando FiberEventLoop)

```php
// Exemplo em HTTP Server baseado em FiberEventLoop
$response = "HTTP/1.1 200 OK\r\n";
$response .= "X-Content-Type-Options: nosniff\r\n";
$response .= "X-Frame-Options: DENY\r\n";
$response .= "X-XSS-Protection: 1; mode=block\r\n";
$response .= "Strict-Transport-Security: max-age=31536000\r\n";
$response .= "Content-Security-Policy: default-src 'self'\r\n";
$response .= "Content-Type: application/json\r\n";
$response .= "\r\n";
```

---

## Testing Security

### Unit Tests
```bash
composer test
```

### Security Audit
```bash
composer audit
```

### Code Analysis
```bash
# Static analysis (se configurado)
composer phpstan
```

---

## Incident Response

1. **Reporte Recebido** → Confirmação em 48h
2. **Triagem** → Validar e avaliar severidade
3. **Desenvolvimento** → Preparar fix
4. **Testing** → Testes de security
5. **Coordenação** → Notificar usuários
6. **Release** → Patch publicado
7. **Disclosure** → Divulgação pública (CVE)
8. **Follow-up** → Monitorar reporte

---

## Disclosed Vulnerabilities

Nenhuma até o momento.

Para histórico, veja:
- [CVE Details](https://www.cvedetails.com/)
- [NVD - NIST](https://nvd.nist.gov/)
- [Packagist Advisories](https://packagist.org/)

---

## Security Resources

### Documentação
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [PHP Security Guide](https://www.php.net/manual/en/security.php)
- [CWE Top 25](https://cwe.mitre.org/top25/)

### Ferramentas
- [Composer Audit](https://getcomposer.org/doc/03-cli.md#audit)
- [PHPStan](https://phpstan.org/)
- [Psalm](https://psalm.dev/)

### Comunidade
- [PHP Security Mailing List](https://www.php.net/unsupported-versions.php)
- [Packagist](https://packagist.org/)

---

## Contato

**Segurança:** security@example.com
**Geral:** webdesenvolver.agenda@gmail.com

---

**Última atualização:** 2024-12-18

Obrigado por ajudar a manter FiberEventLoop seguro! 🔒
