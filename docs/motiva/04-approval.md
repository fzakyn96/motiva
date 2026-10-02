# Approval Architecture & Matrix

- Pisahkan RBAC, Authority, ApprovalPolicy, ApprovalPolicyStep, AuthorityResolution, AuthorityResolver, dan ApprovalResolver.
- Jangan hardcode approver user ID.

Matrix Baseline:
- Memo: Manager (Step 1)
- SPPD: Supervisor (Step 1) -> Manager (Step 2)
- Reimbursement: Supervisor (Step 1) -> Manager (Step 2)