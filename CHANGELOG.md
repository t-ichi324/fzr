# CHANGELOG

## [1.0.0] - 2026-04-20

### Added
- **Unified CLI Architecture**: 全機能を統合した `fzr` コマンドを導入（`init`, `make:model`, `build`）。
- **Modern Scaffolding**: `php fzr init` による対話型プロジェクト初期化エンジンを実装。
- **Modern Directory Structure**: `src/` (PSR-4 Classes) と `inc/` (Functional Helpers) を分離したクリーンな構成。
- **Cloud Native Ready**: GCP/AWS プリセットの導入、およびコンテナ環境向けの `stderr` 自動ロギング対応。
- **Phar Compiler**: フレームワークを単一の `fzr.phar` ファイルにパッケージ化するビルドコマンドを実装。
- **Storage Subsystem**: Local, S3, GCS を抽象化して扱えるドライバベースのストレージ機能。
- **Attribute-based Validation**: PHP 8 アトリビュートを利用したモデル生成とバリデーション機能。

### Changed
- **Installation Flow**: セキュリティリスク排除のため、従来の Web ベースセットアップを廃止し、CLI 完全移行。
- **Template System**: ベーステンプレートの配置を `@layouts` ディレクトリへ変更し、視認性を向上。

---
Fzr (Feather) Framework - Lightweight, Fast, and Pure PHP.
