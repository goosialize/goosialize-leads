<?php
declare(strict_types=1);
namespace Grav\Plugin\GoosializeLeads\Notification;
final class FilesystemDeadLetterRepository implements DeadLetterRepository
{
    public function __construct(private readonly DeliveryStateRepository $states){}
    public function move(DeliveryEventLease $lease,DeliveryState $state,int $expectedRevision):string
    {
        if($state->state()!=='dead_lettered')throw new \RuntimeException('state_transition_invalid');
        if($state->revision()===$expectedRevision+1)$state=$this->states->save($state,$expectedRevision);
        elseif($state->revision()!==$expectedRevision)throw new \RuntimeException('state_revision_stale');
        $current=$this->states->read($state->eventId());
        if($current===null||$current->toArray()!==$state->toArray())throw new \RuntimeException('state_revision_stale');
        $published=$lease->publishDeadLetter();
        if(!in_array($published,['dead_letter_published','dead_letter_existing'],true))return$published;
        if(!$lease->removePending())return'dead_letter_failed';
        return$published==='dead_letter_existing'?'dead_letter_existing':'dead_lettered';
    }
}
