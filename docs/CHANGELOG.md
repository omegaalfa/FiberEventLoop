# Changelog

Todas as mudanças notáveis neste projeto são documentadas neste arquivo.

O formato é baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.0.0/),
e este projeto adere ao [Semantic Versioning](https://semver.org/lang/pt-BR/).

## [Unreleased]

### Added
- Documentação PHPDoc completa para todas as classes e métodos
- Suite abrangente de testes com PHPUnit
  - 24 testes core (`FiberEventLoopTest`)
  - 20 testes de timers (`TimerManagerTraitTest`)
  - 11 testes de streams (`StreamManagerTraitTest`)
  - 17 testes de integração (`IntegrationTest`)
  - 14 testes de performance (`PerformanceTest`)
- Arquivo `phpunit.xml.dist` com configuração de testes
- Arquivo `bootstrap.php` para inicialização de testes
- Documentação `TESTING.md` com guia completo de testes
- Documentação `CONTRIBUTING.md` com diretrizes para contribuidores
- Arquivo `CHANGELOG.md` para rastreamento de versões

### Changed
- README.md completamente reescrito com documentação profissional
  - Adicionados 5 exemplos práticos detalhados
  - Tabelas de comparação com outras soluções
  - Seções de troubleshooting
  - API reference completa
  - Guia de otimizações

### Improved
- Clareza da documentação técnica
- Adicionados comentários de contexto em código
- Melhorada estrutura de PHPDoc em traits
- Adicionados exemplos de uso em todos os métodos públicos

---

## [2.0.0] - 2024-12-18

### Added
- Event loop reativo completo baseado em PHP Fibers
- Suporte a timers: `after()`, `repeat()`, `setInterval()`
- Sleep não-bloqueante com `sleep()` em Fibers
- TCP Server/Client com `listen()`, `onReadable()`, `onWritable()`
- Leitura assíncrona de arquivos com `onReadFile()`
- Gerenciamento de Fibers com priorização
- Deferred callbacks com `defer()`
- Sistema de cancelamento com `cancel()`
- Tratamento robusto de erros com `getErrors()`
- Métricas de performance com `getMetrics()`
- Múltiplos níveis de otimização:
  - `latency` - Mínima latência
  - `throughput` - Máximo throughput
  - `efficient` - Economia de CPU
  - `balanced` - Equilibrado (padrão)
  - `benchmark` - Otimizado para benchmarks
- Idle adaptativo para reduzir CPU em até 90%
- Suporte a múltiplas operações concorrentes
- Zero dependências externas (PHP puro)

### Features

#### Core Event Loop
- ✅ Non-blocking I/O reactor
- ✅ Priority-based execution queue
- ✅ Cooperative multitasking com Fibers
- ✅ Adaptive idle management
- ✅ Comprehensive error capturing

#### Timers
- ✅ One-time execution (`after`)
- ✅ Recurring execution (`repeat`, `setInterval`)
- ✅ Sub-millisecond precision
- ✅ Cooperative sleep (`sleep`)
- ✅ Large-scale timer management (5000+)

#### Streams
- ✅ Non-blocking TCP server
- ✅ Non-blocking TCP client
- ✅ Readable stream monitoring
- ✅ Writable stream monitoring
- ✅ Automatic EOF detection
- ✅ Connection management

#### Performance
- ✅ 50,000+ timers/sec
- ✅ 10,000+ connections/sec
- ✅ 1,500+ HTTP requests/sec
- ✅ 5,000+ file reads/sec
- ✅ Minimal CPU footprint when idle

---

## [1.0.0] - 2024-01-01

### Initial Release
- Basic event loop implementation
- Core timer functionality
- TCP stream support
- File I/O operations
- Error handling

---

## Versioning Policy

Este projeto segue [Semantic Versioning](https://semver.org/):

- **MAJOR**: Breaking changes (incompat com versão anterior)
- **MINOR**: Novas features (compatível com versão anterior)
- **PATCH**: Bug fixes e melhorias (compatível)

### Exemplos

```
1.0.0 -> 1.1.0  (nova feature compatível)
1.1.0 -> 1.1.1  (bug fix)
1.1.1 -> 2.0.0  (breaking change)
```

---

## Roadmap

### Curto Prazo (v2.1.0)
- [ ] WebSocket support
- [ ] HTTP/2 support
- [ ] Connection pooling
- [ ] Rate limiting built-in
- [ ] Request queuing

### Médio Prazo (v2.2.0)
- [ ] TLS/SSL support
- [ ] Graceful shutdown
- [ ] Signal handling
- [ ] Process management
- [ ] Load balancing

### Longo Prazo (v3.0.0)
- [ ] Distributed tracing
- [ ] Metrics export (Prometheus)
- [ ] Service mesh integration
- [ ] Containerization support
- [ ] Kubernetes integration

---

## Deprecated Features

Nenhuma feature foi descontinuada até o momento.

Quando uma feature for descontinuada, será:
1. Marcada como `@deprecated` no PHPDoc
2. Documentada neste changelog
3. Mantida por pelo menos 2 versões menores
4. Removida na próxima versão major

---

## Breaking Changes

### Nenhuma até o momento

Breaking changes serão:
1. Documentadas aqui com detalhe
2. Migração documentada
3. Exemplo de antes/depois

---

## Contributors

Obrigado a todos os contribuidores!

- [OmegaAlfa](https://github.com/omegaalfa) - Autor principal

---

## Security

Para reportar vulnerabilidades, veja [SECURITY.md](SECURITY.md)

---

## Licença

Este arquivo é parte do projeto [FiberEventLoop](https://github.com/omegaalfa/FiberEventLoop)
licenciado sob a licença MIT. Veja [LICENSE](LICENSE) para detalhes.

---

**Última atualização:** 2024-12-18
