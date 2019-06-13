<?php


namespace SessionApprove;


use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ConnectionException;
use Doctrine\DBAL\DBALException;
use SessionApprove\Exception\ProposalConflictException;
use SessionApprove\Exception\SessionNotFoundException;

class SessionRepository
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }


    /**
     * @param int $sessionId
     * @param string|null $proposalId
     * @return void
     * @throws ConnectionException
     * @throws DBALException
     */
    public function approveSession(int $sessionId, ?string $proposalId): void
    {
        $this->connection->beginTransaction();

        $sql = "SELECT accepted_details, proposed_details FROM sessions WHERE id = :id FOR UPDATE";
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue('id', $sessionId);
        $stmt->execute();

        $data = $stmt->fetch();

        if ($data === false) {
            $this->connection->rollBack();
            throw new SessionNotFoundException();
        }

        if ($data['accepted_details'] === $data['proposed_details']) {
            $this->connection->commit();
            return;
        }

        if ($proposalId !== null && $data['proposed_details'] !== $proposalId) {
            $this->connection->commit();
            throw new ProposalConflictException();
        }

        $this->connection->update('sessions', ['accepted_details' => $data['proposed_details']], ['id' => $sessionId]);

        if ($data['accepted_details'] !== null) {
            $this->connection->delete('session_details', ['id' => $data['accepted_details']]);
        }

        $this->connection->commit();
    }
}